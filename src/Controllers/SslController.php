<?php

namespace App\Controllers;

use App\Domains\DomainRepository;
use App\Ssl\SslCheckRepository;
use App\Support\Logger;
use App\Support\SslChecker;
use App\Support\Validator;
use App\Vps\SslRenewer;
use App\Vps\VpsRepository;

class SslController
{
    private const EXPIRING_SOON_DAYS = 14;

    public static function checkDomains(string $domainListRaw): array
    {
        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            $cert = SslChecker::checkCertificate($domain);
            $https = SslChecker::checkHttpsRedirect($domain);

            if (!$cert['ok']) {
                $status = 'error';
                $data = ['error' => $cert['error']];
            } else {
                $daysLeft = $cert['days_left'];
                $status = $daysLeft < 0 ? 'expired' : ($daysLeft <= self::EXPIRING_SOON_DAYS ? 'expiring_soon' : 'ok');
                $data = [
                    'valid_to' => $cert['valid_to'],
                    'days_left' => $daysLeft,
                    'issuer' => $cert['issuer'],
                    'https_ok' => $cert['ok'],
                    'http_redirects_https' => $https['ok'] ? $https['redirects_to_https'] : null,
                ];
            }

            SslCheckRepository::upsert($domain, $status, $data);
            Logger::log('ssl', 'check', $domain, $status === 'error' ? 'error' : ($status === 'ok' ? 'success' : 'warning'), $cert['ok'] ? "còn {$cert['days_left']} ngày" : $cert['error']);

            $results[] = array_merge(['domain' => $domain, 'status' => $status], $cert, ['https_redirect' => $https]);
        }
        return $results;
    }

    public static function checkNamecheapExpiry(string $domainListRaw): array
    {
        $client = NamecheapController::client();
        if (!$client) {
            throw new \RuntimeException('Chưa cấu hình Namecheap API trong Cài đặt.');
        }

        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            try {
                $expiry = $client->getDomainExpiry($domain);
                if ($expiry === null) {
                    $results[] = ['domain' => $domain, 'status' => 'error', 'error' => 'Domain không thuộc account Namecheap này (hoặc không tìm thấy).'];
                    continue;
                }
                $daysLeft = (int) floor((strtotime($expiry) - time()) / 86400);
                $status = $daysLeft < 0 ? 'expired' : ($daysLeft <= 30 ? 'expiring_soon' : 'ok');
                Logger::log('ssl', 'check_registrar_expiry', $domain, $status === 'ok' ? 'success' : 'warning', "hết hạn {$expiry} ({$daysLeft} ngày)");
                $results[] = ['domain' => $domain, 'status' => $status, 'valid_to' => $expiry, 'days_left' => $daysLeft, 'summary' => "Domain hết hạn {$expiry} ({$daysLeft} ngày)"];
            } catch (\Throwable $e) {
                Logger::log('ssl', 'check_registrar_expiry', $domain, 'error', $e->getMessage());
                $results[] = ['domain' => $domain, 'status' => 'error', 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    public static function renewSsl(string $domainListRaw): array
    {
        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            $row = DomainRepository::findByName($domain);
            if (!$row || !$row['vps_id']) {
                $results[] = ['domain' => $domain, 'status' => 'error', 'error' => 'Domain chưa gắn với VPS nào trong hệ thống.'];
                continue;
            }
            $vps = VpsRepository::find((int) $row['vps_id']);
            if (!$vps) {
                $results[] = ['domain' => $domain, 'status' => 'error', 'error' => 'Không tìm thấy VPS đã gắn.'];
                continue;
            }
            try {
                $result = (new SslRenewer($vps))->renew($domain);
                if (!str_contains($result->stdout, 'SAUL_RENEW_OK')) {
                    $method = str_contains($result->stdout, 'SAUL_RENEW_NOT_FOUND')
                        ? 'Không tìm thấy certbot hoặc acme.sh quản lý domain này trên VPS — thử renew qua giao diện aaPanel.'
                        : self::tail($result->stderr . "\n" . $result->stdout);
                    throw new \RuntimeException($method);
                }
                preg_match('/SAUL_RENEW_METHOD=(\w+(\.\w+)?)/', $result->stdout, $m);
                $method = $m[1] ?? 'unknown';
                Logger::log('ssl', 'renew', $domain, 'success', "qua {$method}");
                $results[] = ['domain' => $domain, 'status' => 'ok', 'summary' => "Đã renew qua {$method}"];
            } catch (\Throwable $e) {
                Logger::log('ssl', 'renew', $domain, 'error', $e->getMessage());
                $results[] = ['domain' => $domain, 'status' => 'error', 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    private static function tail(string $text, int $maxLen = 400): string
    {
        $text = trim($text);
        if (strlen($text) <= $maxLen) {
            return $text !== '' ? $text : 'Không có thông báo lỗi.';
        }
        return '...' . substr($text, -$maxLen);
    }
}

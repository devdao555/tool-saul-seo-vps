<?php

namespace App\Support;

/**
 * Pure-PHP SSL certificate + HTTPS config check — no SSH, no VPS needed. Works for any
 * public domain reachable from this server, regardless of which VPS (if any) hosts it.
 */
class SslChecker
{
    public static function checkCertificate(string $domain, int $timeout = 10): array
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'SNI_enabled' => true,
                'peer_name' => $domain,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $client = @stream_socket_client(
            "ssl://{$domain}:443",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$client) {
            return ['ok' => false, 'error' => $errstr !== '' ? $errstr : 'Không kết nối được tới cổng 443.'];
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) {
            return ['ok' => false, 'error' => 'Không lấy được chứng chỉ SSL.'];
        }

        $info = openssl_x509_parse($cert);
        if (!$info) {
            return ['ok' => false, 'error' => 'Không đọc được nội dung chứng chỉ.'];
        }

        $validTo = $info['validTo_time_t'] ?? null;
        $daysLeft = $validTo !== null ? (int) floor(($validTo - time()) / 86400) : null;

        return [
            'ok' => true,
            'valid_from' => isset($info['validFrom_time_t']) ? date('Y-m-d', $info['validFrom_time_t']) : null,
            'valid_to' => $validTo !== null ? date('Y-m-d', $validTo) : null,
            'days_left' => $daysLeft,
            'issuer' => $info['issuer']['O'] ?? ($info['issuer']['CN'] ?? null),
            'common_name' => $info['subject']['CN'] ?? null,
        ];
    }

    public static function checkHttpsRedirect(string $domain, int $timeout = 10): array
    {
        try {
            $result = Http::checkFollowRedirect("http://{$domain}/", $timeout);
            return [
                'ok' => true,
                'redirects_to_https' => str_starts_with($result['effective_url'], 'https://'),
                'final_status' => $result['status'],
                'effective_url' => $result['effective_url'],
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

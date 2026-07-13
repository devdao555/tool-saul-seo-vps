<?php

namespace App\Controllers;

use App\Cloudflare\CfAccountRepository;
use App\Domains\DomainRepository;
use App\Support\Logger;
use App\Support\Validator;

class DnsController
{
    /**
     * Each line: "domain.com IP" -> creates/updates the apex A record and a CNAME for www.
     */
    public static function pushDns(string $rawLines, bool $proxied): array
    {
        $results = [];
        foreach (Validator::lines($rawLines) as $line) {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) !== 2) {
                $results[] = ['line' => $line, 'ok' => false, 'error' => 'Định dạng phải là: domain.com IP'];
                continue;
            }
            [$domain, $ip] = $parts;
            $domain = strtolower($domain);

            if (!Validator::isDomain($domain) || !Validator::isIpv4($ip)) {
                $results[] = ['line' => $line, 'ok' => false, 'error' => 'Domain hoặc IP không hợp lệ.'];
                continue;
            }

            $row = DomainRepository::findByName($domain);
            if (!$row || !$row['zone_id']) {
                $results[] = ['line' => $line, 'domain' => $domain, 'ok' => false, 'error' => 'Domain chưa có zone Cloudflare trong hệ thống. Thêm domain trước.'];
                continue;
            }

            $client = CfAccountRepository::clientFor((int) $row['cf_account_id']);
            try {
                $client->pushARecordWithWww($row['zone_id'], $domain, $ip, $proxied);
                Logger::log('dns', 'push', $domain, 'success', $ip);
                $results[] = ['line' => $line, 'domain' => $domain, 'ok' => true];
            } catch (\Throwable $e) {
                Logger::log('dns', 'push', $domain, 'error', $e->getMessage());
                $results[] = ['line' => $line, 'domain' => $domain, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    public static function deleteDns(string $domainListRaw): array
    {
        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            $row = DomainRepository::findByName($domain);
            if (!$row || !$row['zone_id']) {
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => 'Không tìm thấy zone cho domain này.'];
                continue;
            }
            $client = CfAccountRepository::clientFor((int) $row['cf_account_id']);
            try {
                $count = $client->deleteAllDnsRecords($row['zone_id']);
                Logger::log('dns', 'delete', $domain, 'success', "{$count} record(s)");
                $results[] = ['domain' => $domain, 'ok' => true, 'count' => $count];
            } catch (\Throwable $e) {
                Logger::log('dns', 'delete', $domain, 'error', $e->getMessage());
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }
}

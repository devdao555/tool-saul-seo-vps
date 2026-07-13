<?php

namespace App\Controllers;

use App\Cloudflare\CfAccountRepository;
use App\Domains\DomainRepository;
use App\Support\Logger;
use App\Support\Validator;

class DomainController
{
    public static function addDomains(int $cfAccountId, string $domainListRaw, bool $jumpStart): array
    {
        $account = CfAccountRepository::find($cfAccountId);
        if (!$account) {
            throw new \RuntimeException('Cloudflare account không tồn tại.');
        }
        $client = CfAccountRepository::clientFor($cfAccountId);

        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            try {
                $zone = $client->createZone($domain, $account['account_id'], $jumpStart);
                $ns = $zone['name_servers'] ?? [];
                DomainRepository::upsertZone($domain, $cfAccountId, $zone['id'], $zone['status'] ?? 'pending', $ns[0] ?? null, $ns[1] ?? null);
                Logger::log('cloudflare', 'add_zone', $domain, 'success', implode(', ', $ns));
                $results[] = ['domain' => $domain, 'ok' => true, 'ns' => $ns, 'status' => $zone['status'] ?? 'pending'];
            } catch (\Throwable $e) {
                Logger::log('cloudflare', 'add_zone', $domain, 'error', $e->getMessage());
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    public static function checkNs(string $domainListRaw): array
    {
        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            $row = DomainRepository::findByName($domain);
            if (!$row || !$row['cf_account_id']) {
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => 'Domain chưa được thêm qua tool này (chưa có Cloudflare account gắn kèm).'];
                continue;
            }
            $client = CfAccountRepository::clientFor((int) $row['cf_account_id']);
            try {
                $zone = $client->findZoneByName($domain);
                if (!$zone) {
                    throw new \RuntimeException('Không tìm thấy zone trên Cloudflare.');
                }
                $ns = $zone['name_servers'] ?? [];
                DomainRepository::updateStatusAndNs($domain, $zone['status'], $ns[0] ?? null, $ns[1] ?? null);
                Logger::log('cloudflare', 'check_ns', $domain, 'success', $zone['status']);
                $results[] = ['domain' => $domain, 'ok' => true, 'status' => $zone['status'], 'ns' => $ns];
            } catch (\Throwable $e) {
                Logger::log('cloudflare', 'check_ns', $domain, 'error', $e->getMessage());
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }
}

<?php

namespace App\Controllers;

use App\Cloudflare\CfAccountRepository;
use App\Domains\DomainRepository;
use App\Support\Logger;
use App\Support\Validator;

class RedirectController
{
    public static function createRedirects(string $targetDomain, string $sourceListRaw, int $statusCode = 301): array
    {
        $targetDomain = strtolower(trim($targetDomain));
        if (!Validator::isDomain($targetDomain)) {
            throw new \InvalidArgumentException('Domain đích không hợp lệ.');
        }

        $results = [];
        foreach (Validator::domainList($sourceListRaw) as $source) {
            $row = DomainRepository::findByName($source);
            if (!$row || !$row['zone_id']) {
                $results[] = ['domain' => $source, 'ok' => false, 'error' => 'Không tìm thấy zone Cloudflare cho domain nguồn.'];
                continue;
            }
            $client = CfAccountRepository::clientFor((int) $row['cf_account_id']);
            try {
                $pattern = "*{$source}/*";
                $target = "https://{$targetDomain}/\$2";
                $client->createRedirectPageRule($row['zone_id'], $pattern, $target, $statusCode);
                Logger::log('redirect', 'create', $source, 'success', "-> {$targetDomain}");
                $results[] = ['domain' => $source, 'ok' => true];
            } catch (\Throwable $e) {
                Logger::log('redirect', 'create', $source, 'error', $e->getMessage());
                $results[] = ['domain' => $source, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    public static function deleteRedirects(string $domainListRaw): array
    {
        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            $row = DomainRepository::findByName($domain);
            if (!$row || !$row['zone_id']) {
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => 'Không tìm thấy zone.'];
                continue;
            }
            $client = CfAccountRepository::clientFor((int) $row['cf_account_id']);
            try {
                $count = $client->deleteAllPageRules($row['zone_id']);
                Logger::log('redirect', 'delete', $domain, 'success', "{$count} rule(s)");
                $results[] = ['domain' => $domain, 'ok' => true, 'count' => $count];
            } catch (\Throwable $e) {
                Logger::log('redirect', 'delete', $domain, 'error', $e->getMessage());
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }
}

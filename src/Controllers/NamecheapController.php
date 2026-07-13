<?php

namespace App\Controllers;

use App\Domains\DomainRepository;
use App\Namecheap\NamecheapClient;
use App\Support\Logger;
use App\Support\SettingsRepository;
use App\Support\Validator;

class NamecheapController
{
    public static function client(): ?NamecheapClient
    {
        $apiUser = SettingsRepository::get('namecheap_api_user');
        $apiKey = SettingsRepository::get('namecheap_api_key');
        $clientIp = SettingsRepository::get('namecheap_client_ip');
        if (!$apiUser || !$apiKey || !$clientIp) {
            return null;
        }
        return new NamecheapClient($apiUser, $apiKey, $clientIp);
    }

    public static function pushNameservers(string $domainListRaw): array
    {
        $client = self::client();
        if (!$client) {
            throw new \RuntimeException('Chưa cấu hình Namecheap API trong Cài đặt.');
        }

        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            $row = DomainRepository::findByName($domain);
            if (!$row || !$row['ns1'] || !$row['ns2']) {
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => 'Domain chưa có NS Cloudflare — hãy "Thêm domain" hoặc "Check NS" trước.'];
                continue;
            }
            try {
                $client->setCustomNameservers($domain, [$row['ns1'], $row['ns2']]);
                Logger::log('namecheap', 'push_ns', $domain, 'success', "{$row['ns1']}, {$row['ns2']}");
                $results[] = ['domain' => $domain, 'ok' => true, 'ns' => [$row['ns1'], $row['ns2']]];
            } catch (\Throwable $e) {
                Logger::log('namecheap', 'push_ns', $domain, 'error', $e->getMessage());
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }
}

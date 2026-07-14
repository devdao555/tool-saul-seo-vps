<?php

namespace App\Controllers;

use App\Cloudflare\CfAccountRepository;
use App\Support\Logger;
use App\Support\SettingsRepository;
use App\Support\Validator;

class SettingsController
{
    public static function addCfAccount(string $label, string $apiToken, string $accountId): void
    {
        $label = trim($label);
        $apiToken = trim($apiToken);
        $accountId = trim($accountId);
        if ($label === '' || $apiToken === '' || $accountId === '') {
            throw new \InvalidArgumentException('Vui lòng nhập đủ Label, API Token và Account ID.');
        }
        CfAccountRepository::create($label, $apiToken, $accountId);
        Logger::log('settings', 'add_cf_account', $label, 'success');
    }

    /**
     * Each line: "label|api_token|account_id" — pipe-separated since labels are often
     * emails/free text that could contain spaces.
     */
    public static function bulkAddCfAccounts(string $rawLines): array
    {
        // Never echo the raw line back in results — it contains the API token.
        $results = [];
        foreach (Validator::lines($rawLines) as $line) {
            $parts = array_map('trim', explode('|', $line));
            $label = $parts[0] !== '' ? $parts[0] : '(thiếu label)';

            if (count($parts) !== 3 || $parts[0] === '' || $parts[1] === '' || $parts[2] === '') {
                $results[] = ['domain' => $label, 'ok' => false, 'error' => 'Định dạng phải là: label|api_token|account_id (đủ 3 phần, không để trống).'];
                continue;
            }

            [$label, $apiToken, $accountId] = $parts;
            try {
                CfAccountRepository::create($label, $apiToken, $accountId);
                Logger::log('settings', 'add_cf_account', $label, 'success', 'bulk');
                $results[] = ['domain' => $label, 'ok' => true];
            } catch (\Throwable $e) {
                Logger::log('settings', 'add_cf_account', $label, 'error', $e->getMessage());
                $results[] = ['domain' => $label, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    public static function deleteCfAccount(int $id): void
    {
        CfAccountRepository::delete($id);
        Logger::log('settings', 'delete_cf_account', (string) $id, 'success');
    }

    public static function saveNamecheap(string $apiUser, string $apiKey, string $clientIp): void
    {
        SettingsRepository::set('namecheap_api_user', trim($apiUser));
        // Leave the stored key untouched when the field is left blank, so re-saving
        // the user/IP doesn't wipe out an already-configured API key.
        if (trim($apiKey) !== '') {
            SettingsRepository::set('namecheap_api_key', trim($apiKey));
        }
        SettingsRepository::set('namecheap_client_ip', trim($clientIp));
        Logger::log('settings', 'save_namecheap', null, 'success');
    }
}

<?php

namespace App\Controllers;

use App\Cloudflare\CfAccountRepository;
use App\Support\Logger;
use App\Support\SettingsRepository;

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

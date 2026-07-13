<?php

namespace App\Controllers;

use App\Support\Env;
use App\Support\Logger;
use App\Support\Validator;
use App\Vps\VpsRepository;

class VpsController
{
    public static function addVps(array $input): void
    {
        $label = trim((string) ($input['label'] ?? ''));
        $ip = trim((string) ($input['ip'] ?? ''));
        $sshUser = trim((string) ($input['ssh_user'] ?? 'root'));
        $sshPort = (int) ($input['ssh_port'] ?? 22);
        $phpVersion = preg_replace('/[^0-9]/', '', (string) ($input['php_version'] ?? '81'));
        $webrootBase = trim((string) ($input['webroot_base'] ?? '/www/wwwroot'));
        $mysqlUser = trim((string) ($input['mysql_user'] ?? 'root'));
        $mysqlPassword = (string) ($input['mysql_password'] ?? '');
        $privateKey = (string) ($input['private_key'] ?? '');

        if ($label === '') {
            throw new \InvalidArgumentException('Vui lòng nhập Label.');
        }
        if (!Validator::isIpv4($ip)) {
            throw new \InvalidArgumentException('IP không hợp lệ.');
        }
        if (!Validator::isSafeUsername($sshUser)) {
            throw new \InvalidArgumentException('SSH user không hợp lệ.');
        }
        if ($sshPort < 1 || $sshPort > 65535) {
            throw new \InvalidArgumentException('SSH port không hợp lệ.');
        }
        if (!Validator::isSafePath($webrootBase)) {
            throw new \InvalidArgumentException('Webroot base không hợp lệ.');
        }
        if (!Validator::isSafeUsername($mysqlUser)) {
            throw new \InvalidArgumentException('MySQL user không hợp lệ.');
        }
        if (!str_contains($privateKey, 'PRIVATE KEY')) {
            throw new \InvalidArgumentException('Private key không hợp lệ (thiếu nội dung PEM).');
        }

        $keyFileName = 'vps_' . bin2hex(random_bytes(8)) . '.key';
        $keyDir = ROOT_PATH . '/' . rtrim(Env::get('SSH_KEY_DIR', 'storage/keys'), '/');
        if (!is_dir($keyDir)) {
            mkdir($keyDir, 0770, true);
        }
        $keyPath = $keyDir . '/' . $keyFileName;
        file_put_contents($keyPath, rtrim($privateKey) . "\n");
        @chmod($keyPath, 0600);

        VpsRepository::create([
            'label' => $label,
            'ip' => $ip,
            'ssh_user' => $sshUser,
            'ssh_port' => $sshPort,
            'ssh_key_file' => $keyFileName,
            'php_version' => $phpVersion !== '' ? $phpVersion : '81',
            'webroot_base' => $webrootBase !== '' ? $webrootBase : '/www/wwwroot',
            'mysql_user' => $mysqlUser,
            'mysql_password' => $mysqlPassword,
        ]);

        Logger::log('vps', 'add', $ip, 'success', $label);
    }

    public static function deleteVps(int $id): void
    {
        $vps = VpsRepository::find($id);
        if (!$vps) {
            return;
        }
        $keyPath = \App\Vps\SshClient::resolveKeyPath($vps['ssh_key_file']);
        if (is_file($keyPath)) {
            @unlink($keyPath);
        }
        VpsRepository::delete($id);
        Logger::log('vps', 'delete', $vps['ip'], 'success', $vps['label']);
    }
}

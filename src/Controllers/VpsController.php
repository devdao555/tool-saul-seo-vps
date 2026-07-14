<?php

namespace App\Controllers;

use App\Support\Env;
use App\Support\Logger;
use App\Support\Validator;
use App\Vps\SshBootstrap;
use App\Vps\VpsHealthRepository;
use App\Vps\VpsMonitor;
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

    /**
     * Adds many VPS at once that share the same SSH key / SSH port / PHP version /
     * webroot / MySQL user (the common case for a fleet provisioned the same way) —
     * only label, IP, and MySQL password vary per line.
     * Each line: "label|ip|mysql_password" (mysql_password may be omitted/blank).
     */
    public static function bulkAddVps(array $shared, string $rawLines): array
    {
        $results = [];
        foreach (Validator::lines($rawLines) as $line) {
            $parts = array_map('trim', explode('|', $line));
            $label = ($parts[0] ?? '') !== '' ? $parts[0] : '(thiếu label)';

            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                $results[] = ['domain' => $label, 'ok' => false, 'error' => 'Định dạng phải là: label|ip|mysql_password (mysql_password có thể để trống).'];
                continue;
            }

            [$vpsLabel, $ip] = $parts;
            $mysqlPassword = $parts[2] ?? '';

            try {
                self::addVps([
                    'label' => $vpsLabel,
                    'ip' => $ip,
                    'ssh_user' => $shared['ssh_user'],
                    'ssh_port' => $shared['ssh_port'],
                    'php_version' => $shared['php_version'],
                    'webroot_base' => $shared['webroot_base'],
                    'mysql_user' => $shared['mysql_user'],
                    'mysql_password' => $mysqlPassword,
                    'private_key' => $shared['private_key'],
                ]);
                $results[] = ['domain' => $vpsLabel, 'ok' => true];
            } catch (\Throwable $e) {
                $results[] = ['domain' => $vpsLabel, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    /**
     * One-time onboarding for VPS that only have SSH password auth: generates a fresh
     * keypair, uses each VPS's existing password once to install the new public key, and
     * returns the private key for the admin to use in bulkAddVps()/addVps() afterwards.
     * Each line: "ip|ssh_password".
     */
    public static function bulkBootstrapSsh(string $sshUser, int $sshPort, string $rawLines): array
    {
        $keypair = SshBootstrap::generateKeypair();
        $bootstrap = new SshBootstrap($sshUser, $sshPort);

        $results = [];
        foreach (Validator::lines($rawLines) as $line) {
            $parts = array_map('trim', explode('|', $line));
            $ipLabel = ($parts[0] ?? '') !== '' ? $parts[0] : '(thiếu IP)';

            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                $results[] = ['domain' => $ipLabel, 'ok' => false, 'error' => 'Định dạng phải là: ip|ssh_password.'];
                continue;
            }

            [$ip, $sshPassword] = $parts;

            try {
                $result = $bootstrap->installKey($ip, $sshPassword, $keypair['public']);
                if (!str_contains($result->stdout, 'SAUL_BOOTSTRAP_OK')) {
                    throw new \RuntimeException(self::tail($result->stderr . "\n" . $result->stdout));
                }
                Logger::log('vps', 'bootstrap_ssh', $ip, 'success');
                $results[] = ['domain' => $ip, 'ok' => true];
            } catch (\Throwable $e) {
                Logger::log('vps', 'bootstrap_ssh', $ip, 'error', $e->getMessage());
                $results[] = ['domain' => $ip, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        return ['private_key' => $keypair['private'], 'public_key' => $keypair['public'], 'results' => $results];
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

    public static function checkHealth(int $id): array
    {
        $vps = VpsRepository::find($id);
        if (!$vps) {
            throw new \RuntimeException('VPS không tồn tại.');
        }
        $health = (new VpsMonitor($vps))->checkHealth();
        VpsHealthRepository::upsert($id, $health);

        $status = empty($health['reachable']) ? 'error' : (empty($health['error']) ? 'success' : 'warning');
        Logger::log('vps', 'health_check', $vps['ip'], $status, $health['error'] ?? "CPU {$health['cpu_percent']}% · RAM {$health['ram_percent']}% · Disk {$health['disk_percent']}%");

        return $health;
    }

    public static function checkAllHealth(): array
    {
        $results = [];
        foreach (VpsRepository::all() as $vps) {
            $health = (new VpsMonitor($vps))->checkHealth();
            VpsHealthRepository::upsert((int) $vps['id'], $health);
            $status = empty($health['reachable']) ? 'error' : (empty($health['error']) ? 'success' : 'warning');
            Logger::log('vps', 'health_check', $vps['ip'], $status, $health['error'] ?? "CPU {$health['cpu_percent']}% · RAM {$health['ram_percent']}% · Disk {$health['disk_percent']}%");
            $results[(int) $vps['id']] = $health;
        }
        return $results;
    }

    public static function restartService(int $id, string $serviceKey): void
    {
        $vps = VpsRepository::find($id);
        if (!$vps) {
            throw new \RuntimeException('VPS không tồn tại.');
        }
        $result = (new VpsMonitor($vps))->restartService($serviceKey);
        if (!$result->ok()) {
            Logger::log('vps', 'restart_service', $vps['ip'], 'error', "{$serviceKey}: " . trim($result->stderr . ' ' . $result->stdout));
            throw new \RuntimeException("Restart {$serviceKey} thất bại: " . trim($result->stderr . ' ' . $result->stdout));
        }
        Logger::log('vps', 'restart_service', $vps['ip'], 'success', $serviceKey);
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

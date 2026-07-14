<?php

namespace App\Vps;

use App\Support\Validator;

/**
 * One-time onboarding helper for VPS that only have SSH password auth so far: generates a
 * fresh keypair and uses the existing password (once) to install the new public key into
 * `~/.ssh/authorized_keys`, so the VPS can then be added normally via SshClient's
 * key-based path. Requires the `sshpass` binary on the machine running this app (not
 * installed by default — `apt install sshpass` on Debian/Ubuntu).
 *
 * The password is passed to `sshpass` via the SSHPASS environment variable (`-e` flag),
 * never on the command line, so it never shows up in `ps aux` on this or the remote host.
 */
class SshBootstrap
{
    public function __construct(
        private string $sshUser,
        private int $sshPort
    ) {
    }

    /**
     * Generates a new ed25519 keypair by shelling out to the local `ssh-keygen` binary.
     * Returns the key contents directly; the temp files on disk are deleted immediately.
     */
    public static function generateKeypair(): array
    {
        $tmpBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'saul_bootstrap_' . bin2hex(random_bytes(6));

        $args = ['ssh-keygen', '-t', 'ed25519', '-f', $tmpBase, '-N', '', '-q'];
        $command = implode(' ', array_map('escapeshellarg', $args));
        $result = SshClient::execute($command, null, [], 30);

        $privateKey = @file_get_contents($tmpBase);
        $publicKey = @file_get_contents($tmpBase . '.pub');
        @unlink($tmpBase);
        @unlink($tmpBase . '.pub');

        if ($privateKey === false || $publicKey === false) {
            throw new \RuntimeException('Không tạo được SSH keypair (ssh-keygen): ' . trim($result->stderr . ' ' . $result->stdout));
        }

        return ['private' => trim($privateKey), 'public' => trim($publicKey)];
    }

    /**
     * Logs into $ip once using the given password and appends $publicKey to
     * ~/.ssh/authorized_keys (idempotent — safe to run again with the same or a new key).
     */
    public function installKey(string $ip, string $sshPassword, string $publicKey): SshResult
    {
        if (!Validator::isIpv4($ip)) {
            throw new \InvalidArgumentException("IP không hợp lệ: {$ip}");
        }

        $keyLine = SshClient::bashQuote($publicKey);
        $script = <<<BASH
set -e
mkdir -p ~/.ssh
chmod 700 ~/.ssh
touch ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
grep -qF {$keyLine} ~/.ssh/authorized_keys || echo {$keyLine} >> ~/.ssh/authorized_keys
echo "SAUL_BOOTSTRAP_OK"
BASH;

        $sshArgs = [
            'sshpass', '-e', 'ssh',
            '-p', (string) $this->sshPort,
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'ConnectTimeout=15',
            '-o', 'PreferredAuthentications=password,keyboard-interactive',
            '-o', 'PubkeyAuthentication=no',
            "{$this->sshUser}@{$ip}",
            'bash -s',
        ];
        $command = implode(' ', array_map('escapeshellarg', $sshArgs));

        return SshClient::execute($command, $script, ['SSHPASS' => $sshPassword], 30);
    }
}

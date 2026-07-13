<?php

namespace App\Vps;

use App\Support\Env;

/**
 * Shells out to the system `ssh` binary (key-based auth only) instead of pulling in a
 * Composer SSH library, so the app stays dependency-free and easy to deploy on aaPanel.
 * Requires the OpenSSH client to be installed on the machine running this PHP app
 * (present by default on Linux and on Windows 10/11).
 */
class SshClient
{
    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $keyFile
    ) {
    }

    public static function forVps(array $vps): self
    {
        return new self($vps['ip'], (int) $vps['ssh_port'], $vps['ssh_user'], self::resolveKeyPath($vps['ssh_key_file']));
    }

    public static function resolveKeyPath(string $keyFile): string
    {
        if (str_starts_with($keyFile, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $keyFile)) {
            return $keyFile;
        }
        $dir = rtrim(Env::get('SSH_KEY_DIR', 'storage/keys'), '/\\');
        return ROOT_PATH . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $keyFile;
    }

    /**
     * Runs a bash script on the remote host by piping it to `bash -s` over SSH stdin.
     * This avoids fragile command-line quoting for anything longer than a one-liner.
     */
    public function runScript(string $bashScript, int $timeout = 180): SshResult
    {
        $sshArgs = [
            'ssh',
            '-i', $this->keyFile,
            '-p', (string) $this->port,
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'ConnectTimeout=15',
            $this->user . '@' . $this->host,
            'bash -s',
        ];
        $command = implode(' ', array_map('escapeshellarg', $sshArgs));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Không khởi động được tiến trình ssh.');
        }

        fwrite($pipes[0], $bashScript);
        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $start = time();

        while (true) {
            $status = proc_get_status($process);
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            if (!$status['running']) {
                break;
            }
            if (time() - $start > $timeout) {
                proc_terminate($process);
                $stderr .= "\n[timeout after {$timeout}s]";
                break;
            }
            usleep(150000);
        }

        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return new SshResult($exitCode, $stdout, $stderr);
    }

    public static function bashQuote(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }
}

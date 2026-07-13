<?php

namespace App\Vps;

/**
 * Lightweight VPS health check over SSH: CPU/RAM/disk/load + a fixed set of known
 * service names (nginx, mysql, redis, php-fpm). CPU% is sampled with a 1-second delta
 * read of /proc/stat inside the same SSH round trip, so a single call takes ~1s+latency.
 *
 * Service restart is intentionally limited to this same fixed whitelist — never pass an
 * arbitrary/user-supplied service name into a remote systemctl call.
 */
class VpsMonitor
{
    private const RESTARTABLE = ['nginx', 'mysql', 'redis', 'php-fpm'];

    public function __construct(private array $vps)
    {
    }

    private function q(string $value): string
    {
        return SshClient::bashQuote($value);
    }

    private function serviceCandidates(): array
    {
        $phpVer = preg_replace('/[^0-9]/', '', (string) $this->vps['php_version']);
        return [
            'nginx' => ['nginx'],
            'mysql' => ['mysqld', 'mysql', 'mariadb'],
            'redis' => ['redis', 'redis-server'],
            'php-fpm' => ["php-fpm-{$phpVer}", "php{$phpVer}-fpm", 'php-fpm'],
        ];
    }

    /**
     * TCP-connect reachability check against the SSH port. Doesn't need SSH auth to
     * succeed, so it also tells you "wrong key" apart from "VPS unreachable".
     */
    public function isReachable(int $timeoutSeconds = 5): bool
    {
        $conn = @fsockopen($this->vps['ip'], (int) $this->vps['ssh_port'], $errno, $errstr, $timeoutSeconds);
        if ($conn) {
            fclose($conn);
            return true;
        }
        return false;
    }

    public function checkHealth(): array
    {
        if (!$this->isReachable()) {
            return ['reachable' => false, 'error' => 'Không kết nối được tới cổng SSH (VPS có thể đang down hoặc firewall chặn).'];
        }

        $serviceLines = [];
        foreach ($this->serviceCandidates() as $key => $candidates) {
            $args = implode(' ', array_map(fn ($c) => $this->q($c), $candidates));
            $serviceLines[] = "echo \"SVC_{$key}=\$(check_svc {$args})\"";
        }
        $serviceBlock = implode("\n", $serviceLines);

        $script = <<<BASH
set +e
check_svc() {
  for name in "\$@"; do
    st=\$(systemctl is-active "\$name" 2>/dev/null)
    if [ "\$st" = "active" ]; then echo "active"; return; fi
  done
  echo "inactive"
}

read cpu user nice system idle iowait irq softirq steal guest guest_nice < /proc/stat
prev_idle=\$((idle + iowait))
prev_total=\$((user + nice + system + idle + iowait + irq + softirq + steal))
sleep 1
read cpu user nice system idle iowait irq softirq steal guest guest_nice < /proc/stat
idle_now=\$((idle + iowait))
total_now=\$((user + nice + system + idle + iowait + irq + softirq + steal))
diff_idle=\$((idle_now - prev_idle))
diff_total=\$((total_now - prev_total))
if [ "\$diff_total" -gt 0 ]; then
  cpu_pct=\$(( (diff_total - diff_idle) * 100 / diff_total ))
else
  cpu_pct=0
fi
echo "CPU_PCT=\$cpu_pct"

free -m | awk '/^Mem:/{print "RAM_TOTAL="\$2; print "RAM_USED="\$3}'
df -Pk / | awk 'NR==2{print "DISK_TOTAL_KB="\$2; print "DISK_USED_KB="\$3; gsub("%","",\$5); print "DISK_PCT="\$5}'
awk '{print "LOAD_AVG="\$1" "\$2" "\$3}' /proc/loadavg
up=\$(uptime -p 2>/dev/null)
echo "UPTIME=\${up:-unknown}"

{$serviceBlock}
echo "===SAUL_HEALTH_OK==="
BASH;

        $result = SshClient::forVps($this->vps)->runScript($script, 60);

        if (!str_contains($result->stdout, 'SAUL_HEALTH_OK')) {
            return ['reachable' => true, 'error' => self::tail($result->stderr . "\n" . $result->stdout)];
        }

        return array_merge(['reachable' => true, 'error' => null], $this->parse($result->stdout));
    }

    public function restartService(string $serviceKey): SshResult
    {
        if (!in_array($serviceKey, self::RESTARTABLE, true)) {
            throw new \InvalidArgumentException("Dịch vụ không được phép: {$serviceKey}");
        }
        $candidates = $this->serviceCandidates()[$serviceKey];

        $tries = [];
        foreach ($candidates as $name) {
            $q = $this->q($name);
            $tries[] = "systemctl restart {$q} 2>/dev/null && echo \"RESTARTED={$name}\" && exit 0";
        }
        $script = "set +e\n" . implode("\n", $tries) . "\necho \"RESTART_FAILED\"\nexit 1\n";

        return SshClient::forVps($this->vps)->runScript($script, 30);
    }

    private function parse(string $text): array
    {
        $int = static function (string $key, string $text): ?int {
            return preg_match('/^' . $key . '=(-?\d+)$/m', $text, $m) ? (int) $m[1] : null;
        };

        $ramTotal = $int('RAM_TOTAL', $text);
        $ramUsed = $int('RAM_USED', $text);
        $diskTotalKb = $int('DISK_TOTAL_KB', $text);
        $diskUsedKb = $int('DISK_USED_KB', $text);

        $loadAvg = preg_match('/^LOAD_AVG=(.+)$/m', $text, $m) ? trim($m[1]) : null;
        $uptime = preg_match('/^UPTIME=(.*)$/m', $text, $m) ? trim($m[1]) : null;

        $services = [];
        if (preg_match_all('/^SVC_([a-z-]+)=(\w+)$/m', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $services[$m[1]] = $m[2];
            }
        }

        return [
            'cpu_percent' => $int('CPU_PCT', $text),
            'ram_total_mb' => $ramTotal,
            'ram_used_mb' => $ramUsed,
            'ram_percent' => ($ramTotal && $ramTotal > 0) ? (int) round($ramUsed / $ramTotal * 100) : null,
            'disk_total_gb' => $diskTotalKb !== null ? round($diskTotalKb / 1048576, 1) : null,
            'disk_used_gb' => $diskUsedKb !== null ? round($diskUsedKb / 1048576, 1) : null,
            'disk_percent' => $int('DISK_PCT', $text),
            'load_avg' => $loadAvg,
            'uptime' => $uptime,
            'services' => $services,
        ];
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

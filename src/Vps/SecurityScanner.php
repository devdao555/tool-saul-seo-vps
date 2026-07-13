<?php

namespace App\Vps;

use App\Support\Validator;

/**
 * Best-effort malware/backdoor scanner for a WordPress site (or a whole VPS worth of
 * sites) over SSH. Two zero-install layers, no extra software required on the VPS:
 *
 *  1. `wp core verify-checksums` — compares core files against the official WordPress.org
 *     checksums. Very reliable for core, but a non-zero exit can also mean "no outbound
 *     internet on this VPS" or "checksums unavailable for this version", not just
 *     "hacked" — surface the raw detail so the admin can judge, don't over-claim.
 *  2. A heuristic grep across all .php files for common webshell/backdoor patterns
 *     (encoded eval(), direct exec of $_GET/$_POST, dynamic "$_GET['x']()" calls, the old
 *     preg_replace /e RCE trick) plus known webshell filenames. This is a heuristic, not
 *     a real antivirus — it can miss obfuscated malware and can flag legitimate code that
 *     happens to match a pattern. Treat matches as "worth a manual look", not a verdict.
 */
class SecurityScanner
{
    private const HEURISTIC_PATTERN = '(eval|assert)\s*\(\s*(base64_decode|gzinflate|gzuncompress|gzdecode|str_rot13)\s*\(|(assert|passthru|shell_exec|system|popen|proc_open)\s*\(\s*\$_(POST|GET|REQUEST|COOKIE)|\$_(POST|GET|REQUEST|COOKIE)\s*\[[^\]]+\]\s*\(|preg_replace\s*\([^)]*/e[\'"]';

    private const SUSPICIOUS_NAME_GLOBS = [
        'c99*.php', 'r57*.php', 'wso*.php', 'b374k*.php', 'alfa*.php',
        'indoxploit*.php', 'mini*.php', '*.php.suspected',
    ];

    public function __construct(private array $vps)
    {
    }

    private function webroot(string $domain): string
    {
        return rtrim($this->vps['webroot_base'], '/') . '/' . $domain;
    }

    private function q(string $value): string
    {
        return SshClient::bashQuote($value);
    }

    private function assertDomain(string $domain): void
    {
        if (!Validator::isDomain($domain)) {
            throw new \InvalidArgumentException("Domain không hợp lệ: {$domain}");
        }
    }

    /**
     * @param string $pathExpr a ready-to-use bash path expression WITH trailing slash,
     *                         either a quoted literal (single site) or "$d" (loop var).
     */
    private function scanBlock(string $pathExpr): string
    {
        $pattern = $this->q(self::HEURISTIC_PATTERN);
        $nameConditions = implode(' -o ', array_map(
            fn ($glob) => '-iname ' . $this->q($glob),
            self::SUSPICIOUS_NAME_GLOBS
        ));

        return <<<BASH
if [ -f {$pathExpr}wp-load.php ]; then
  echo "IS_WP=1"
  echo "---CHECKSUM---"
  wp core verify-checksums --allow-root --path={$pathExpr} 2>&1
  echo "CHECKSUM_EXIT=\$?"
else
  echo "IS_WP=0"
fi
echo "---HEURISTIC---"
grep -rlP {$pattern} --include=*.php {$pathExpr} 2>/dev/null | head -n 50
echo "---SUSPICIOUS_NAMES---"
find {$pathExpr} -type f \( {$nameConditions} \) 2>/dev/null | head -n 50
BASH;
    }

    public function scanSite(string $domain): array
    {
        $this->assertDomain($domain);
        $pathExpr = $this->q($this->webroot($domain) . '/');
        $block = $this->scanBlock($pathExpr);

        $script = <<<BASH
set +e
if [ ! -d {$pathExpr} ]; then
  echo "SAUL_NO_DIR"
  exit 0
fi
{$block}
BASH;

        $result = SshClient::forVps($this->vps)->runScript($script, 120);

        if (str_contains($result->stdout, 'SAUL_NO_DIR')) {
            return ['domain' => $domain, 'status' => 'error', 'error' => 'Không tìm thấy thư mục site trên VPS.'];
        }
        if (!str_contains($result->stdout, 'IS_WP=')) {
            return ['domain' => $domain, 'status' => 'error', 'error' => self::tail($result->stderr . "\n" . $result->stdout)];
        }

        return array_merge(['domain' => $domain], $this->parseBlock($result->stdout));
    }

    /**
     * Discovers every directory under the VPS's webroot_base and scans each in a single
     * SSH round trip (instead of one connection per domain).
     */
    public function scanAllOnVps(): array
    {
        $base = $this->q(rtrim($this->vps['webroot_base'], '/') . '/');
        $block = $this->scanBlock('"$d"');

        $script = <<<BASH
set +e
for d in {$base}*/; do
  [ -d "\$d" ] || continue
  domain=\$(basename "\$d")
  echo "===SAUL_DOMAIN_START:\$domain==="
{$block}
  echo "===SAUL_DOMAIN_END==="
done
echo "===SAUL_SCAN_ALL_DONE==="
BASH;

        $result = SshClient::forVps($this->vps)->runScript($script, 600);

        if (!str_contains($result->stdout, 'SAUL_SCAN_ALL_DONE')) {
            throw new \RuntimeException(self::tail($result->stderr . "\n" . $result->stdout));
        }

        $chunks = [];
        if (preg_match_all('/===SAUL_DOMAIN_START:(.+?)===\n(.*?)\n===SAUL_DOMAIN_END===/s', $result->stdout, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $chunks[] = array_merge(['domain' => trim($m[1])], $this->parseBlock($m[2]));
            }
        }
        return $chunks;
    }

    private function parseBlock(string $text): array
    {
        $isWp = (bool) preg_match('/^IS_WP=1$/m', $text);

        $checksumExit = null;
        $checksumDetail = '';
        if ($isWp && preg_match('/---CHECKSUM---\n(.*?)\nCHECKSUM_EXIT=(\d+)/s', $text, $m)) {
            $checksumDetail = trim($m[1]);
            $checksumExit = (int) $m[2];
        }

        $heuristicMatches = [];
        if (preg_match('/---HEURISTIC---\n(.*?)\n---SUSPICIOUS_NAMES---/s', $text, $m)) {
            $heuristicMatches = Validator::lines($m[1]);
        }

        $suspiciousNames = [];
        if (preg_match('/---SUSPICIOUS_NAMES---\n(.*)$/s', $text, $m)) {
            $suspiciousNames = Validator::lines($m[1]);
        }

        $status = 'clean';
        if (!$isWp) {
            $status = 'not_wp';
        }
        if ($isWp && $checksumExit !== null && $checksumExit !== 0) {
            $status = 'suspicious';
        }
        if (!empty($heuristicMatches) || !empty($suspiciousNames)) {
            $status = 'suspicious';
        }

        return [
            'status' => $status,
            'is_wp' => $isWp,
            'checksum_exit' => $checksumExit,
            'checksum_detail' => $checksumDetail,
            'heuristic_matches' => $heuristicMatches,
            'suspicious_names' => $suspiciousNames,
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

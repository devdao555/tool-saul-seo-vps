<?php

namespace App\Controllers;

use App\Cloudflare\CfAccountRepository;
use App\Domains\DomainRepository;
use App\Support\Logger;
use App\Support\Validator;
use App\Vps\VpsRepository;
use App\Vps\WordPressManager;

class WordPressController
{
    public static function createBlankSites(int $vpsId, string $domainListRaw, string $adminUser, string $adminPassword, string $adminEmail): array
    {
        $vps = VpsRepository::find($vpsId);
        if (!$vps) {
            throw new \RuntimeException('VPS không tồn tại.');
        }
        $manager = new WordPressManager($vps);

        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            try {
                $result = $manager->createBlankSite($domain, $adminUser, $adminPassword, $adminEmail);
                if (!$result->ok()) {
                    throw new \RuntimeException(self::tail($result->stderr . "\n" . $result->stdout));
                }
                DomainRepository::assignVps($domain, $vpsId);
                Logger::log('wordpress', 'create', $domain, 'success', "VPS #{$vpsId}");
                $results[] = ['domain' => $domain, 'ok' => true];
            } catch (\Throwable $e) {
                Logger::log('wordpress', 'create', $domain, 'error', $e->getMessage());
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    /**
     * @param string $mappingRaw lines of "old.com new.com"
     */
    public static function cloneSites(int $targetVpsId, string $mappingRaw, bool $closeIndexing): array
    {
        $vps = VpsRepository::find($targetVpsId);
        if (!$vps) {
            throw new \RuntimeException('VPS đích không tồn tại.');
        }
        $manager = new WordPressManager($vps);

        $results = [];
        foreach (Validator::lines($mappingRaw) as $line) {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) !== 2) {
                $results[] = ['line' => $line, 'ok' => false, 'error' => 'Định dạng phải là: old.com new.com'];
                continue;
            }
            [$source, $target] = array_map('strtolower', $parts);
            if (!Validator::isDomain($source) || !Validator::isDomain($target)) {
                $results[] = ['line' => $line, 'ok' => false, 'error' => 'Domain không hợp lệ.'];
                continue;
            }
            try {
                $result = $manager->cloneSite($source, $target, $closeIndexing);
                if (!$result->ok()) {
                    throw new \RuntimeException(self::tail($result->stderr . "\n" . $result->stdout));
                }
                DomainRepository::assignVps($target, $targetVpsId);
                Logger::log('wordpress', 'clone', "{$source} -> {$target}", 'success');
                $results[] = ['line' => $line, 'domain' => $target, 'ok' => true];
            } catch (\Throwable $e) {
                Logger::log('wordpress', 'clone', "{$source} -> {$target}", 'error', $e->getMessage());
                $results[] = ['line' => $line, 'domain' => $target, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    /**
     * Rebuild a fingerprinted site: fresh WP + a theme/plugin stack installed from ZIP URLs
     * the operator supplies (license-holding copies) plus free wp.org plugin slugs.
     *
     * @param string $themeZipsRaw   one ZIP URL per line
     * @param string $pluginSlugsRaw one wp.org slug per line (or comma/space separated)
     * @param string $pluginZipsRaw  one ZIP URL per line
     */
    public static function createFromTemplate(
        int $vpsId,
        string $domain,
        string $adminUser,
        string $adminPassword,
        string $adminEmail,
        string $themeZipsRaw,
        string $activateTheme,
        string $pluginSlugsRaw,
        string $pluginZipsRaw,
        string $demoXmlUrl = '',
        string $wpressUrl = ''
    ): array {
        $vps = VpsRepository::find($vpsId);
        if (!$vps) {
            throw new \RuntimeException('VPS không tồn tại.');
        }
        $domain = strtolower(trim($domain));
        if (!Validator::isDomain($domain)) {
            throw new \InvalidArgumentException('Domain không hợp lệ.');
        }

        $themeZips = Validator::lines($themeZipsRaw);
        $pluginZips = Validator::lines($pluginZipsRaw);
        $pluginSlugs = array_values(array_filter(preg_split('/[\s,]+/', trim($pluginSlugsRaw)) ?: []));

        $manager = new WordPressManager($vps);
        try {
            $result = $manager->createSiteFromTemplate(
                $domain,
                $adminUser,
                $adminPassword,
                $adminEmail,
                $themeZips,
                trim($activateTheme),
                $pluginSlugs,
                $pluginZips,
                trim($demoXmlUrl),
                trim($wpressUrl)
            );
            if (!$result->ok()) {
                throw new \RuntimeException(self::tail($result->stderr . "\n" . $result->stdout));
            }
            DomainRepository::assignVps($domain, $vpsId);
            Logger::log('wordpress', 'rebuild', $domain, 'success', "VPS #{$vpsId}, theme={$activateTheme}");
            return [['domain' => $domain, 'ok' => true]];
        } catch (\Throwable $e) {
            Logger::log('wordpress', 'rebuild', $domain, 'error', $e->getMessage());
            return [['domain' => $domain, 'ok' => false, 'error' => $e->getMessage()]];
        }
    }

    public static function deleteSites(string $domainListRaw): array
    {
        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            $row = DomainRepository::findByName($domain);
            if (!$row || !$row['vps_id']) {
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => 'Domain chưa gắn với VPS nào trong hệ thống.'];
                continue;
            }
            $vps = VpsRepository::find((int) $row['vps_id']);
            if (!$vps) {
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => 'Không tìm thấy VPS đã gắn.'];
                continue;
            }
            try {
                $manager = new WordPressManager($vps);
                $result = $manager->deleteSite($domain);
                if (!$result->ok()) {
                    throw new \RuntimeException(self::tail($result->stderr . "\n" . $result->stdout));
                }

                $dnsMessage = '';
                if ($row['zone_id'] && $row['cf_account_id']) {
                    try {
                        $client = CfAccountRepository::clientFor((int) $row['cf_account_id']);
                        $count = $client?->deleteAllDnsRecords($row['zone_id']) ?? 0;
                        $dnsMessage = "; đã xoá {$count} DNS record";
                    } catch (\Throwable $e) {
                        $dnsMessage = '; không xoá được DNS: ' . $e->getMessage();
                    }
                }

                DomainRepository::delete($domain);
                Logger::log('wordpress', 'delete', $domain, 'success', 'Đã xoá site trên VPS' . $dnsMessage);
                $results[] = ['domain' => $domain, 'ok' => true];
            } catch (\Throwable $e) {
                Logger::log('wordpress', 'delete', $domain, 'error', $e->getMessage());
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    public static function changePassword(string $domain, string $username, string $newPassword): void
    {
        $domain = strtolower(trim($domain));
        if (!Validator::isDomain($domain)) {
            throw new \InvalidArgumentException('Domain không hợp lệ.');
        }
        $row = DomainRepository::findByName($domain);
        if (!$row || !$row['vps_id']) {
            throw new \RuntimeException('Domain chưa gắn với VPS nào trong hệ thống.');
        }
        $vps = VpsRepository::find((int) $row['vps_id']);
        if (!$vps) {
            throw new \RuntimeException('Không tìm thấy VPS đã gắn.');
        }
        $manager = new WordPressManager($vps);
        $result = $manager->changeAdminPassword($domain, $username, $newPassword);
        if (!$result->ok()) {
            throw new \RuntimeException(self::tail($result->stderr . "\n" . $result->stdout));
        }
        Logger::log('wordpress', 'change_password', $domain, 'success', $username);
    }

    public static function clearCache(string $domainListRaw): array
    {
        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            $row = DomainRepository::findByName($domain);
            if (!$row || !$row['vps_id']) {
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => 'Domain chưa gắn với VPS nào trong hệ thống.'];
                continue;
            }
            $vps = VpsRepository::find((int) $row['vps_id']);
            if (!$vps) {
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => 'Không tìm thấy VPS đã gắn.'];
                continue;
            }
            try {
                $manager = new WordPressManager($vps);
                $result = $manager->clearCache($domain);
                if (!$result->ok()) {
                    throw new \RuntimeException(self::tail($result->stderr . "\n" . $result->stdout));
                }
                Logger::log('wordpress', 'clear_cache', $domain, 'success');
                $results[] = ['domain' => $domain, 'ok' => true];
            } catch (\Throwable $e) {
                Logger::log('wordpress', 'clear_cache', $domain, 'error', $e->getMessage());
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    /**
     * Bulk-deploys the SAUL AI Writer plugin onto every domain in the list (each domain is
     * resolved to its own VPS). One shared AI config + keyword list is pushed to all sites.
     */
    public static function deployAiWriter(string $domainListRaw, array $settings, string $keywordsRaw, bool $runNow): array
    {
        $provider = $settings['provider'] ?? '';
        if (!in_array($provider, ['openai', 'claude', 'gemini'], true)) {
            throw new \InvalidArgumentException('Nhà cung cấp AI không hợp lệ.');
        }
        if (trim((string) ($settings['api_key'] ?? '')) === '') {
            throw new \InvalidArgumentException('API key không được để trống.');
        }
        $model = trim((string) ($settings['model'] ?? ''));
        if ($model !== '' && !preg_match('#^[A-Za-z0-9._/-]{1,100}$#', $model)) {
            throw new \InvalidArgumentException('Tên model không hợp lệ.');
        }

        $payload = [
            'provider'      => $provider,
            'api_key'       => trim((string) $settings['api_key']),
            'model'         => $model,
            'language'      => trim((string) ($settings['language'] ?? 'Tiếng Việt')),
            'posts_per_day' => max(1, min(48, (int) ($settings['posts_per_day'] ?? 2))),
            'post_status'   => in_array($settings['post_status'] ?? '', ['publish', 'draft', 'pending'], true)
                ? $settings['post_status'] : 'publish',
            'category'      => trim((string) ($settings['category'] ?? '')),
            'prompt_extra'  => trim((string) ($settings['prompt_extra'] ?? '')),
            'image_provider' => in_array($settings['image_provider'] ?? '', ['none', 'openai', 'gemini'], true)
                ? $settings['image_provider'] : 'none',
            'image_api_key'  => trim((string) ($settings['image_api_key'] ?? '')),
        ];
        $keywords = Validator::lines($keywordsRaw);

        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            $row = DomainRepository::findByName($domain);
            if (!$row || !$row['vps_id']) {
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => 'Domain chưa gắn với VPS nào trong hệ thống.'];
                continue;
            }
            $vps = VpsRepository::find((int) $row['vps_id']);
            if (!$vps) {
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => 'Không tìm thấy VPS đã gắn.'];
                continue;
            }
            try {
                $manager = new WordPressManager($vps);
                $result = $manager->deployAiWriter($domain, $payload, $keywords, $runNow);
                if (!$result->ok()) {
                    throw new \RuntimeException(self::tail($result->stderr . "\n" . $result->stdout));
                }
                Logger::log('wordpress', 'ai_writer', $domain, 'success', count($keywords) . ' từ khoá, ' . $payload['posts_per_day'] . ' bài/ngày');
                $results[] = ['domain' => $domain, 'ok' => true];
            } catch (\Throwable $e) {
                Logger::log('wordpress', 'ai_writer', $domain, 'error', $e->getMessage());
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    private static function tail(string $text, int $maxLen = 400): string
    {
        $text = trim($text);
        if (strlen($text) <= $maxLen) {
            return $text !== '' ? $text : 'Lệnh thất bại không có thông báo lỗi.';
        }
        return '...' . substr($text, -$maxLen);
    }
}

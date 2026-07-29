<?php

namespace App\Controllers;

use App\Cloudflare\CfAccountRepository;
use App\Domains\DomainRepository;
use App\Support\Logger;
use App\Support\Validator;
use App\Vps\VpsRepository;

class DnsController
{
    /**
     * Each line: "domain.com IP" -> creates/updates the apex A record and a CNAME for www.
     */
    public static function pushDns(string $rawLines, bool $proxied): array
    {
        $results = [];
        foreach (Validator::lines($rawLines) as $line) {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) !== 2) {
                $results[] = ['line' => $line, 'ok' => false, 'error' => 'Định dạng phải là: domain.com IP'];
                continue;
            }
            [$domain, $ip] = $parts;
            $domain = strtolower($domain);

            $type = null;
            if (Validator::isIpv4($ip)) {
                $type = 'A';
            } elseif (Validator::isIpv6($ip)) {
                $type = 'AAAA';
            }

            if (!Validator::isDomain($domain) || $type === null) {
                $results[] = ['line' => $line, 'ok' => false, 'error' => 'Domain hoặc IP (v4/v6) không hợp lệ.'];
                continue;
            }

            $row = DomainRepository::findByName($domain);
            if (!$row || !$row['zone_id']) {
                $results[] = ['line' => $line, 'domain' => $domain, 'ok' => false, 'error' => 'Domain chưa có zone Cloudflare trong hệ thống. Thêm domain trước.'];
                continue;
            }

            $client = CfAccountRepository::clientFor((int) $row['cf_account_id']);
            try {
                $client->pushIpRecordWithWww($row['zone_id'], $domain, $ip, $type, $proxied);
                Logger::log('dns', 'push', $domain, 'success', "{$type} {$ip}");
                $results[] = ['line' => $line, 'domain' => $domain, 'ok' => true];
            } catch (\Throwable $e) {
                Logger::log('dns', 'push', $domain, 'error', $e->getMessage());
                $results[] = ['line' => $line, 'domain' => $domain, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    /**
     * Each line: "sub.domain.com TARGET" -> creates/updates a single record for that exact
     * hostname. TARGET decides the type: IPv4 -> A, IPv6 -> AAAA, hostname -> CNAME. The zone
     * is resolved from the hostname's parent domain (managed domains in the DB first, then a
     * live Cloudflare lookup as a fallback).
     */
    public static function pushSubdomains(string $rawLines, bool $proxied): array
    {
        $results = [];
        foreach (Validator::lines($rawLines) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) !== 2) {
                $results[] = ['line' => $line, 'ok' => false, 'error' => 'Định dạng phải là: sub.domain.com IP-hoặc-hostname'];
                continue;
            }
            [$host, $target] = $parts;
            $host = strtolower($host);
            $target = strtolower($target);

            if (!self::isSubdomain($host)) {
                $results[] = ['line' => $line, 'ok' => false, 'error' => 'Host phải là subdomain hợp lệ (vd: blog.domain.com).'];
                continue;
            }

            $type = null;
            if (Validator::isIpv4($target)) {
                $type = 'A';
            } elseif (Validator::isIpv6($target)) {
                $type = 'AAAA';
            } elseif (self::isHostname($target)) {
                $type = 'CNAME';
            }
            if ($type === null) {
                $results[] = ['line' => $line, 'ok' => false, 'error' => 'Target phải là IPv4, IPv6 hoặc hostname (cho CNAME).'];
                continue;
            }

            $zone = self::resolveZoneForHost($host);
            if (!$zone) {
                $results[] = ['line' => $line, 'domain' => $host, 'ok' => false, 'error' => 'Không tìm thấy zone Cloudflare cho domain gốc của host này.'];
                continue;
            }

            try {
                $zone['client']->upsertDnsRecord($zone['zone_id'], $type, $host, $target, $proxied);
                Logger::log('dns', 'push_subdomain', $host, 'success', "{$type} {$target}");
                $results[] = ['line' => $line, 'domain' => $host, 'ok' => true];
            } catch (\Throwable $e) {
                Logger::log('dns', 'push_subdomain', $host, 'error', $e->getMessage());
                $results[] = ['line' => $line, 'domain' => $host, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    /**
     * Each line: a single subdomain hostname -> deletes every DNS record with that exact name
     * (all types) in its zone, without touching the rest of the domain's records.
     */
    public static function deleteSubdomains(string $rawLines): array
    {
        $results = [];
        foreach (Validator::lines($rawLines) as $line) {
            $host = strtolower(trim($line));
            if (!self::isSubdomain($host)) {
                $results[] = ['domain' => $line, 'ok' => false, 'error' => 'Phải là subdomain hợp lệ (vd: blog.domain.com).'];
                continue;
            }

            $zone = self::resolveZoneForHost($host);
            if (!$zone) {
                $results[] = ['domain' => $host, 'ok' => false, 'error' => 'Không tìm thấy zone Cloudflare cho domain gốc của host này.'];
                continue;
            }

            try {
                $count = $zone['client']->deleteDnsRecordsByName($zone['zone_id'], $host);
                Logger::log('dns', 'delete_subdomain', $host, 'success', "{$count} record(s)");
                $results[] = ['domain' => $host, 'ok' => true, 'count' => $count];
            } catch (\Throwable $e) {
                Logger::log('dns', 'delete_subdomain', $host, 'error', $e->getMessage());
                $results[] = ['domain' => $host, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    private static function isHostname(string $value): bool
    {
        return (bool) preg_match('/^(?=.{1,253}$)([a-z0-9](-?[a-z0-9])*\.)+[a-z]{2,}$/i', $value);
    }

    private static function isSubdomain(string $value): bool
    {
        return self::isHostname($value) && substr_count($value, '.') >= 2;
    }

    /**
     * Walk the host's parent suffixes (longest first) and return the Cloudflare zone that
     * contains it: check managed domains in the DB first, then query every configured
     * Cloudflare account as a fallback. Returns ['zone_id' => ..., 'client' => ...] or null.
     *
     * @return array{zone_id:string, client:\App\Cloudflare\CloudflareClient}|null
     */
    private static function resolveZoneForHost(string $host): ?array
    {
        $labels = explode('.', $host);
        $candidates = [];
        for ($i = 1; $i < count($labels) - 1; $i++) {
            $candidates[] = implode('.', array_slice($labels, $i));
        }

        foreach ($candidates as $candidate) {
            $row = DomainRepository::findByName($candidate);
            if ($row && $row['zone_id'] && $row['cf_account_id']) {
                $client = CfAccountRepository::clientFor((int) $row['cf_account_id']);
                if ($client) {
                    return ['zone_id' => $row['zone_id'], 'client' => $client];
                }
            }
        }

        foreach (CfAccountRepository::all() as $account) {
            $client = CfAccountRepository::clientFor((int) $account['id']);
            if (!$client) {
                continue;
            }
            foreach ($candidates as $candidate) {
                try {
                    $zone = $client->findZoneByName($candidate);
                    if ($zone) {
                        return ['zone_id' => $zone['id'], 'client' => $client];
                    }
                } catch (\Throwable) {
                    // try next candidate / account
                }
            }
        }

        return null;
    }

    public static function deleteDns(string $domainListRaw): array
    {
        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            $row = DomainRepository::findByName($domain);
            if (!$row || !$row['zone_id']) {
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => 'Không tìm thấy zone cho domain này.'];
                continue;
            }
            $client = CfAccountRepository::clientFor((int) $row['cf_account_id']);
            try {
                $count = $client->deleteAllDnsRecords($row['zone_id']);
                Logger::log('dns', 'delete', $domain, 'success', "{$count} record(s)");
                $results[] = ['domain' => $domain, 'ok' => true, 'count' => $count];
            } catch (\Throwable $e) {
                Logger::log('dns', 'delete', $domain, 'error', $e->getMessage());
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    public static function purgeCache(string $domainListRaw): array
    {
        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            $row = DomainRepository::findByName($domain);
            if (!$row || !$row['zone_id']) {
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => 'Không tìm thấy zone cho domain này.'];
                continue;
            }
            $client = CfAccountRepository::clientFor((int) $row['cf_account_id']);
            try {
                $client->purgeCache($row['zone_id'], true);
                Logger::log('dns', 'purge_cache', $domain, 'success');
                $results[] = ['domain' => $domain, 'ok' => true];
            } catch (\Throwable $e) {
                Logger::log('dns', 'purge_cache', $domain, 'error', $e->getMessage());
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    public static function toggleProxy(string $domainListRaw, bool $proxied): array
    {
        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            $row = DomainRepository::findByName($domain);
            if (!$row || !$row['zone_id']) {
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => 'Không tìm thấy zone cho domain này.'];
                continue;
            }
            $client = CfAccountRepository::clientFor((int) $row['cf_account_id']);
            try {
                $count = $client->setAllRecordsProxied($row['zone_id'], $proxied);
                $label = $proxied ? 'bật' : 'tắt';
                Logger::log('dns', 'toggle_proxy', $domain, 'success', "{$label} proxy, {$count} record(s)");
                $results[] = ['domain' => $domain, 'ok' => true, 'count' => $count];
            } catch (\Throwable $e) {
                Logger::log('dns', 'toggle_proxy', $domain, 'error', $e->getMessage());
                $results[] = ['domain' => $domain, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    /**
     * Compares the live apex A/AAAA record for every managed domain against the IP of its
     * assigned VPS (when one is assigned), and flags domains with no A/AAAA record at all.
     */
    public static function scanDnsHealth(): array
    {
        $results = [];
        foreach (DomainRepository::all() as $row) {
            if (!$row['zone_id']) {
                continue;
            }
            $client = CfAccountRepository::clientFor((int) $row['cf_account_id']);
            if (!$client) {
                $results[] = ['domain' => $row['domain'], 'ok' => false, 'error' => 'Không có Cloudflare account gắn kèm.'];
                continue;
            }
            try {
                $records = $client->listDnsRecords($row['zone_id']);
                $ipRecord = null;
                foreach ($records as $record) {
                    if (in_array($record['type'], ['A', 'AAAA'], true) && $record['name'] === $row['domain']) {
                        $ipRecord = $record;
                        break;
                    }
                }

                if (!$ipRecord) {
                    $results[] = ['domain' => $row['domain'], 'ok' => false, 'error' => 'Không có A/AAAA record cho domain gốc.'];
                    continue;
                }

                if ($row['vps_id']) {
                    $vps = VpsRepository::find((int) $row['vps_id']);
                    if ($vps && $ipRecord['content'] !== $vps['ip']) {
                        $results[] = ['domain' => $row['domain'], 'ok' => false, 'error' => "DNS đang trỏ {$ipRecord['content']} nhưng VPS gán trong hệ thống là {$vps['ip']}."];
                        continue;
                    }
                }

                $results[] = ['domain' => $row['domain'], 'ok' => true];
            } catch (\Throwable $e) {
                $results[] = ['domain' => $row['domain'], 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }
}

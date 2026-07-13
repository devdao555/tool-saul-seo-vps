<?php

namespace App\Controllers;

use App\Domains\DomainRepository;
use App\Security\SecurityScanRepository;
use App\Support\Logger;
use App\Support\Validator;
use App\Vps\SecurityScanner;
use App\Vps\VpsRepository;

class SecurityController
{
    public static function scanDomains(string $domainListRaw): array
    {
        $results = [];
        foreach (Validator::domainList($domainListRaw) as $domain) {
            $row = DomainRepository::findByName($domain);
            if (!$row || !$row['vps_id']) {
                $results[] = ['domain' => $domain, 'status' => 'error', 'error' => 'Domain chưa gắn với VPS nào trong hệ thống (tạo/clone WP trước, hoặc gán VPS thủ công).'];
                continue;
            }
            $vps = VpsRepository::find((int) $row['vps_id']);
            if (!$vps) {
                $results[] = ['domain' => $domain, 'status' => 'error', 'error' => 'Không tìm thấy VPS đã gắn.'];
                continue;
            }

            try {
                $result = (new SecurityScanner($vps))->scanSite($domain);
                $summary = self::summarize($result);
                SecurityScanRepository::upsert($domain, (int) $vps['id'], $result['status'], $summary, $result);
                Logger::log('security', 'scan', $domain, self::logStatus($result['status']), $summary);
                $results[] = $result + ['summary' => $summary];
            } catch (\Throwable $e) {
                Logger::log('security', 'scan', $domain, 'error', $e->getMessage());
                $results[] = ['domain' => $domain, 'status' => 'error', 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    /**
     * Discovers and scans every site directory found on the VPS (not just domains this
     * tool already knows about), in one SSH round trip.
     */
    public static function scanVps(int $vpsId): array
    {
        $vps = VpsRepository::find($vpsId);
        if (!$vps) {
            throw new \RuntimeException('VPS không tồn tại.');
        }

        $rawResults = (new SecurityScanner($vps))->scanAllOnVps();

        $results = [];
        foreach ($rawResults as $result) {
            $summary = self::summarize($result);
            SecurityScanRepository::upsert($result['domain'], $vpsId, $result['status'], $summary, $result);
            Logger::log('security', 'scan', $result['domain'], self::logStatus($result['status']), $summary);
            $results[] = $result + ['summary' => $summary];
        }
        return $results;
    }

    private static function logStatus(string $scanStatus): string
    {
        return match ($scanStatus) {
            'suspicious' => 'warning',
            'error' => 'error',
            default => 'success',
        };
    }

    private static function summarize(array $result): string
    {
        if (($result['status'] ?? '') === 'error') {
            return $result['error'] ?? 'Lỗi không xác định.';
        }

        $parts = [];
        if (($result['checksum_exit'] ?? 0) !== 0 && ($result['is_wp'] ?? false)) {
            $parts[] = 'checksum core lệch/không xác minh được';
        }
        if (!empty($result['heuristic_matches'])) {
            $parts[] = count($result['heuristic_matches']) . ' file nghi ngờ (heuristic)';
        }
        if (!empty($result['suspicious_names'])) {
            $parts[] = count($result['suspicious_names']) . ' file tên đáng ngờ';
        }

        if ($parts) {
            return implode(', ', $parts);
        }

        return ($result['is_wp'] ?? false)
            ? 'Sạch — checksum khớp, không phát hiện heuristic.'
            : 'Không phải site WordPress — chỉ quét heuristic, không phát hiện gì.';
    }
}

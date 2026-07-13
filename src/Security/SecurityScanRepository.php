<?php

namespace App\Security;

use App\Support\Database;

class SecurityScanRepository
{
    public static function upsert(string $domain, ?int $vpsId, string $status, string $summary, array $detail): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO security_scans (domain, vps_id, status, summary, detail, scanned_at)
             VALUES (:domain, :vps_id, :status, :summary, :detail, datetime("now"))
             ON CONFLICT(domain) DO UPDATE SET
                vps_id = excluded.vps_id,
                status = excluded.status,
                summary = excluded.summary,
                detail = excluded.detail,
                scanned_at = excluded.scanned_at'
        );
        $stmt->execute([
            'domain' => $domain,
            'vps_id' => $vpsId,
            'status' => $status,
            'summary' => $summary,
            'detail' => json_encode($detail, JSON_UNESCAPED_SLASHES),
        ]);
    }

    public static function all(): array
    {
        $sql = 'SELECT s.*, v.label AS vps_label
                FROM security_scans s
                LEFT JOIN vps v ON v.id = s.vps_id
                ORDER BY s.scanned_at DESC';
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function find(string $domain): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM security_scans WHERE domain = ?');
        $stmt->execute([$domain]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function latestStatusByDomain(): array
    {
        $rows = Database::connection()->query('SELECT domain, status, scanned_at FROM security_scans')->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[$row['domain']] = $row;
        }
        return $map;
    }
}

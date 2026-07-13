<?php

namespace App\Vps;

use App\Support\Database;

class VpsHealthRepository
{
    public static function upsert(int $vpsId, array $health): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO vps_health (vps_id, reachable, cpu_percent, ram_percent, ram_used_mb, ram_total_mb,
                disk_percent, disk_used_gb, disk_total_gb, load_avg, uptime, services, error, checked_at)
             VALUES (:vps_id, :reachable, :cpu_percent, :ram_percent, :ram_used_mb, :ram_total_mb,
                :disk_percent, :disk_used_gb, :disk_total_gb, :load_avg, :uptime, :services, :error, datetime("now"))
             ON CONFLICT(vps_id) DO UPDATE SET
                reachable = excluded.reachable,
                cpu_percent = excluded.cpu_percent,
                ram_percent = excluded.ram_percent,
                ram_used_mb = excluded.ram_used_mb,
                ram_total_mb = excluded.ram_total_mb,
                disk_percent = excluded.disk_percent,
                disk_used_gb = excluded.disk_used_gb,
                disk_total_gb = excluded.disk_total_gb,
                load_avg = excluded.load_avg,
                uptime = excluded.uptime,
                services = excluded.services,
                error = excluded.error,
                checked_at = excluded.checked_at'
        );
        $stmt->execute([
            'vps_id' => $vpsId,
            'reachable' => !empty($health['reachable']) ? 1 : 0,
            'cpu_percent' => $health['cpu_percent'] ?? null,
            'ram_percent' => $health['ram_percent'] ?? null,
            'ram_used_mb' => $health['ram_used_mb'] ?? null,
            'ram_total_mb' => $health['ram_total_mb'] ?? null,
            'disk_percent' => $health['disk_percent'] ?? null,
            'disk_used_gb' => $health['disk_used_gb'] ?? null,
            'disk_total_gb' => $health['disk_total_gb'] ?? null,
            'load_avg' => $health['load_avg'] ?? null,
            'uptime' => $health['uptime'] ?? null,
            'services' => isset($health['services']) ? json_encode($health['services']) : null,
            'error' => $health['error'] ?? null,
        ]);
    }

    public static function all(): array
    {
        $rows = Database::connection()->query('SELECT * FROM vps_health')->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $row['services'] = $row['services'] ? json_decode($row['services'], true) : [];
            $map[(int) $row['vps_id']] = $row;
        }
        return $map;
    }
}

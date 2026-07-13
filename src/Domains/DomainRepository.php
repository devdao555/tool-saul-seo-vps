<?php

namespace App\Domains;

use App\Support\Database;

class DomainRepository
{
    public static function all(): array
    {
        $sql = 'SELECT d.*, c.label AS cf_account_label, v.label AS vps_label,
                       s.status AS security_status, s.scanned_at AS security_scanned_at
                FROM domains d
                LEFT JOIN cf_accounts c ON c.id = d.cf_account_id
                LEFT JOIN vps v ON v.id = d.vps_id
                LEFT JOIN security_scans s ON s.domain = d.domain
                ORDER BY d.domain';
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function findByName(string $domain): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM domains WHERE domain = ?');
        $stmt->execute([$domain]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function upsertZone(string $domain, int $cfAccountId, string $zoneId, string $status, ?string $ns1, ?string $ns2): void
    {
        $existing = self::findByName($domain);
        if ($existing) {
            $stmt = Database::connection()->prepare(
                'UPDATE domains SET cf_account_id = :cf, zone_id = :zone, status = :status, ns1 = :ns1, ns2 = :ns2, updated_at = datetime("now") WHERE domain = :domain'
            );
        } else {
            $stmt = Database::connection()->prepare(
                'INSERT INTO domains (domain, cf_account_id, zone_id, status, ns1, ns2) VALUES (:domain, :cf, :zone, :status, :ns1, :ns2)'
            );
        }
        $stmt->execute([
            'domain' => $domain,
            'cf' => $cfAccountId,
            'zone' => $zoneId,
            'status' => $status,
            'ns1' => $ns1,
            'ns2' => $ns2,
        ]);
    }

    public static function updateStatusAndNs(string $domain, string $status, ?string $ns1, ?string $ns2): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE domains SET status = :status, ns1 = :ns1, ns2 = :ns2, updated_at = datetime("now") WHERE domain = :domain'
        );
        $stmt->execute(['domain' => $domain, 'status' => $status, 'ns1' => $ns1, 'ns2' => $ns2]);
    }

    public static function assignVps(string $domain, ?int $vpsId): void
    {
        self::ensureExists($domain);
        $stmt = Database::connection()->prepare('UPDATE domains SET vps_id = :vps, updated_at = datetime("now") WHERE domain = :domain');
        $stmt->execute(['vps' => $vpsId, 'domain' => $domain]);
    }

    /**
     * Inserts a bare row for the domain if it isn't tracked yet, so VPS/WordPress actions
     * work even for domains that were never run through the Cloudflare "add zone" flow.
     */
    public static function ensureExists(string $domain): void
    {
        if (self::findByName($domain)) {
            return;
        }
        $stmt = Database::connection()->prepare('INSERT INTO domains (domain) VALUES (?)');
        $stmt->execute([$domain]);
    }

    public static function delete(string $domain): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM domains WHERE domain = ?');
        $stmt->execute([$domain]);
    }
}

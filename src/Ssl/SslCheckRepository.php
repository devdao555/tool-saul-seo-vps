<?php

namespace App\Ssl;

use App\Support\Database;

class SslCheckRepository
{
    public static function upsert(string $domain, string $status, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO ssl_checks (domain, status, valid_to, days_left, issuer, https_ok, http_redirects_https, error, checked_at)
             VALUES (:domain, :status, :valid_to, :days_left, :issuer, :https_ok, :redirects, :error, datetime("now"))
             ON CONFLICT(domain) DO UPDATE SET
                status = excluded.status,
                valid_to = excluded.valid_to,
                days_left = excluded.days_left,
                issuer = excluded.issuer,
                https_ok = excluded.https_ok,
                http_redirects_https = excluded.http_redirects_https,
                error = excluded.error,
                checked_at = excluded.checked_at'
        );
        $stmt->execute([
            'domain' => $domain,
            'status' => $status,
            'valid_to' => $data['valid_to'] ?? null,
            'days_left' => $data['days_left'] ?? null,
            'issuer' => $data['issuer'] ?? null,
            'https_ok' => isset($data['https_ok']) ? (int) $data['https_ok'] : null,
            'redirects' => isset($data['http_redirects_https']) ? (int) $data['http_redirects_https'] : null,
            'error' => $data['error'] ?? null,
        ]);
    }

    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM ssl_checks ORDER BY days_left ASC')->fetchAll();
    }
}

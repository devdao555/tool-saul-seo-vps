<?php

namespace App\Cloudflare;

use App\Support\Crypto;
use App\Support\Database;

class CfAccountRepository
{
    public static function all(): array
    {
        $stmt = Database::connection()->query('SELECT id, label, account_id, created_at FROM cf_accounts ORDER BY label');
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM cf_accounts WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function clientFor(int $id): ?CloudflareClient
    {
        $row = self::find($id);
        if (!$row) {
            return null;
        }
        return new CloudflareClient(Crypto::decrypt($row['api_token_enc']));
    }

    public static function create(string $label, string $apiToken, string $accountId): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cf_accounts (label, api_token_enc, account_id) VALUES (:label, :token, :account_id)'
        );
        $stmt->execute([
            'label' => $label,
            'token' => Crypto::encrypt($apiToken),
            'account_id' => $accountId,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM cf_accounts WHERE id = ?');
        $stmt->execute([$id]);
    }
}

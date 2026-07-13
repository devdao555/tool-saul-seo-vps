<?php

namespace App\Support;

class SettingsRepository
{
    public static function get(string $key): ?string
    {
        $stmt = Database::connection()->prepare('SELECT value_enc FROM settings WHERE key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null || $value === '') {
            return null;
        }
        return Crypto::decrypt($value);
    }

    public static function set(string $key, string $value): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO settings (key, value_enc) VALUES (:key, :value)
             ON CONFLICT(key) DO UPDATE SET value_enc = excluded.value_enc'
        );
        $stmt->execute(['key' => $key, 'value' => Crypto::encrypt($value)]);
    }
}

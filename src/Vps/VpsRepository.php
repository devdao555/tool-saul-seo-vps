<?php

namespace App\Vps;

use App\Support\Crypto;
use App\Support\Database;

class VpsRepository
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM vps ORDER BY label')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM vps WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function mysqlPassword(array $vps): string
    {
        return $vps['mysql_password_enc'] ? Crypto::decrypt($vps['mysql_password_enc']) : '';
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO vps (label, ip, ssh_user, ssh_port, ssh_key_file, php_version, webroot_base, mysql_user, mysql_password_enc)
             VALUES (:label, :ip, :ssh_user, :ssh_port, :ssh_key_file, :php_version, :webroot_base, :mysql_user, :mysql_password_enc)'
        );
        $stmt->execute([
            'label' => $data['label'],
            'ip' => $data['ip'],
            'ssh_user' => $data['ssh_user'],
            'ssh_port' => $data['ssh_port'],
            'ssh_key_file' => $data['ssh_key_file'],
            'php_version' => $data['php_version'],
            'webroot_base' => $data['webroot_base'],
            'mysql_user' => $data['mysql_user'],
            'mysql_password_enc' => $data['mysql_password'] !== '' ? Crypto::encrypt($data['mysql_password']) : null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM vps WHERE id = ?');
        $stmt->execute([$id]);
    }
}

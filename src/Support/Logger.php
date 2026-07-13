<?php

namespace App\Support;

class Logger
{
    public static function log(string $module, string $action, ?string $target, string $status, ?string $message = null): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO system_logs (module, action, target, status, message) VALUES (:module, :action, :target, :status, :message)'
        );
        $stmt->execute([
            'module' => $module,
            'action' => $action,
            'target' => $target,
            'status' => $status,
            'message' => $message,
        ]);
    }

    public static function recent(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM system_logs ORDER BY id DESC LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

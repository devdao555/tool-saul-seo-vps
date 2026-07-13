<?php

namespace App\Controllers;

use App\Support\Database;
use App\Support\Logger;

class DashboardController
{
    public static function stats(): array
    {
        $pdo = Database::connection();
        return [
            'domains' => (int) $pdo->query('SELECT COUNT(*) FROM domains')->fetchColumn(),
            'active_domains' => (int) $pdo->query("SELECT COUNT(*) FROM domains WHERE status = 'active'")->fetchColumn(),
            'cf_accounts' => (int) $pdo->query('SELECT COUNT(*) FROM cf_accounts')->fetchColumn(),
            'vps' => (int) $pdo->query('SELECT COUNT(*) FROM vps')->fetchColumn(),
        ];
    }

    public static function recentLogs(int $limit = 20): array
    {
        return Logger::recent($limit);
    }
}

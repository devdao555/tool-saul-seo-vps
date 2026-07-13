<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Support\Database;
use App\Support\Env;

$schema = file_get_contents(__DIR__ . '/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "Could not read schema.sql\n");
    exit(1);
}

Database::connection()->exec($schema);

echo "Database migrated successfully at " . Env::get('DB_PATH', 'storage/db.sqlite') . "\n";

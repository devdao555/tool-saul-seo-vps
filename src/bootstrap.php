<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $relative = substr($class, strlen('App\\'));
    $path = ROOT_PATH . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

use App\Support\Env;

Env::load(ROOT_PATH);

date_default_timezone_set(Env::get('APP_TIMEZONE', 'UTC'));

$isProduction = Env::get('APP_ENV', 'production') === 'production';
ini_set('display_errors', $isProduction ? '0' : '1');
error_reporting(E_ALL);

foreach (['storage', 'storage/logs', 'storage/keys'] as $dir) {
    $full = ROOT_PATH . '/' . $dir;
    if (!is_dir($full)) {
        mkdir($full, 0770, true);
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

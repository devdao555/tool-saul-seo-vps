<?php

namespace App\Support;

class Database
{
    private static ?\PDO $pdo = null;

    public static function connection(): \PDO
    {
        if (self::$pdo === null) {
            $path = Env::get('DB_PATH', 'storage/db.sqlite');
            if (!str_starts_with($path, '/') && !preg_match('/^[A-Za-z]:\\\\/', $path)) {
                $path = ROOT_PATH . DIRECTORY_SEPARATOR . $path;
            }
            self::$pdo = new \PDO('sqlite:' . $path);
            self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            self::$pdo->exec('PRAGMA foreign_keys = ON;');
        }
        return self::$pdo;
    }
}

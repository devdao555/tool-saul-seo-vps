<?php

namespace App\Auth;

use App\Support\Env;

class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $expectedUser = Env::get('ADMIN_USERNAME', 'admin');
        $expectedHash = Env::get('ADMIN_PASSWORD_HASH', '');

        if ($expectedHash === '' || !hash_equals($expectedUser, $username)) {
            return false;
        }
        if (!password_verify($password, $expectedHash)) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['auth_user'] = $username;
        return true;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['auth_user']);
    }

    public static function user(): ?string
    {
        return $_SESSION['auth_user'] ?? null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function requireLogin(string $loginUrl): void
    {
        if (!self::check()) {
            header('Location: ' . $loginUrl);
            exit;
        }
    }
}

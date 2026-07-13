<?php

namespace App\Support;

/**
 * Simple session-based flash storage so POST handlers can redirect (POST-redirect-GET)
 * and still show the result table / error message on the next page load.
 */
class Flash
{
    public static function set(string $key, mixed $value): void
    {
        $_SESSION['flash'][$key] = $value;
    }

    public static function pull(string $key): mixed
    {
        $value = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $value;
    }
}

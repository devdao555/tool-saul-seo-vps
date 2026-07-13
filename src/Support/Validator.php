<?php

namespace App\Support;

/**
 * Strict validators used everywhere user input reaches a shell command or an API call,
 * to prevent command/argument injection via domain, IP, or username fields.
 */
class Validator
{
    public static function isDomain(string $value): bool
    {
        return (bool) preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $value);
    }

    public static function isIpv4(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    public static function isSafeUsername(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{1,60}$/', $value);
    }

    public static function isSafePath(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_\-\.\/]{1,255}$/', $value) && !str_contains($value, '..');
    }

    /**
     * Splits a textarea into trimmed, non-empty lines.
     */
    public static function lines(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $lines = array_map('trim', $lines);
        return array_values(array_filter($lines, fn ($line) => $line !== ''));
    }

    public static function domainList(string $raw): array
    {
        $domains = [];
        foreach (self::lines($raw) as $line) {
            $domain = strtolower($line);
            if (self::isDomain($domain)) {
                $domains[] = $domain;
            }
        }
        return array_values(array_unique($domains));
    }
}

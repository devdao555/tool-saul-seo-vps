<?php

namespace App\Support;

class Crypto
{
    private const CIPHER = 'aes-256-cbc';

    private static function key(): string
    {
        $key = Env::get('APP_KEY', '');
        if ($key === '' || $key === 'change_this_to_a_random_32_char_secret') {
            throw new \RuntimeException('APP_KEY is not set. Generate one and put it in .env before storing secrets.');
        }
        return hash('sha256', $key, true);
    }

    public static function encrypt(string $plainText): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);
        $cipherText = openssl_encrypt($plainText, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
        if ($cipherText === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return base64_encode($iv . $cipherText);
    }

    public static function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            throw new \RuntimeException('Invalid encrypted payload.');
        }
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($raw, 0, $ivLength);
        $cipherText = substr($raw, $ivLength);
        $plainText = openssl_decrypt($cipherText, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
        if ($plainText === false) {
            throw new \RuntimeException('Decryption failed. APP_KEY may have changed.');
        }
        return $plainText;
    }
}

<?php

namespace App\Support;

/**
 * Minimal cURL wrapper. Kept dependency-free on purpose so the app needs no Composer step.
 */
class Http
{
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 30): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("HTTP request failed: {$error}");
        }
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $statusCode, 'body' => $responseBody];
    }

    /**
     * HEAD-style request that follows redirects, for checking where a URL ends up
     * (e.g. does http:// redirect to https://) without downloading the response body.
     */
    public static function checkFollowRedirect(string $url, int $timeout = 10): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'SaulSeoTool/1.0',
        ]);

        $success = curl_exec($ch);
        if ($success === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("HTTP request failed: {$error}");
        }

        $info = [
            'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'effective_url' => curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
        ];
        curl_close($ch);

        return $info;
    }

    public static function json(string $method, string $url, array $headers, ?array $payload = null): array
    {
        $headers[] = 'Content-Type: application/json';
        $body = $payload !== null ? json_encode($payload) : null;
        $response = self::request($method, $url, $headers, $body);
        $decoded = json_decode($response['body'], true);
        return [
            'status' => $response['status'],
            'data' => $decoded,
            'raw' => $response['body'],
        ];
    }
}

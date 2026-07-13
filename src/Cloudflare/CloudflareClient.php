<?php

namespace App\Cloudflare;

use App\Support\Http;

class CloudflareClient
{
    private const BASE = 'https://api.cloudflare.com/client/v4';

    public function __construct(private string $apiToken)
    {
    }

    private function headers(): array
    {
        return ['Authorization: Bearer ' . $this->apiToken];
    }

    /**
     * @throws CloudflareException
     */
    private function call(string $method, string $path, ?array $payload = null): array
    {
        $response = Http::json($method, self::BASE . $path, $this->headers(), $payload);
        $data = $response['data'];

        if (!is_array($data) || ($data['success'] ?? false) !== true) {
            $errors = is_array($data['errors'] ?? null) ? $data['errors'] : [];
            $messages = array_map(fn ($e) => $e['message'] ?? json_encode($e), $errors);
            $message = $messages ? implode('; ', $messages) : ('HTTP ' . $response['status']);
            throw new CloudflareException($message, $response['status']);
        }

        return $data;
    }

    public function listAccounts(): array
    {
        $data = $this->call('GET', '/accounts?per_page=50');
        return array_map(fn ($a) => ['id' => $a['id'], 'name' => $a['name']], $data['result'] ?? []);
    }

    /**
     * Creates a zone for the domain under the given Cloudflare account and returns the assigned nameservers.
     */
    public function createZone(string $domain, string $accountId, bool $jumpStart = false): array
    {
        $data = $this->call('POST', '/zones', [
            'name' => $domain,
            'account' => ['id' => $accountId],
            'jump_start' => $jumpStart,
        ]);
        return $data['result'];
    }

    public function findZoneByName(string $domain): ?array
    {
        $data = $this->call('GET', '/zones?name=' . urlencode($domain));
        $result = $data['result'] ?? [];
        return $result[0] ?? null;
    }

    public function deleteZone(string $zoneId): void
    {
        $this->call('DELETE', '/zones/' . $zoneId);
    }

    public function listDnsRecords(string $zoneId): array
    {
        $data = $this->call('GET', '/zones/' . $zoneId . '/dns_records?per_page=200');
        return $data['result'] ?? [];
    }

    public function createDnsRecord(string $zoneId, string $type, string $name, string $content, bool $proxied = true, int $ttl = 1): array
    {
        $data = $this->call('POST', '/zones/' . $zoneId . '/dns_records', [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'proxied' => $proxied,
            'ttl' => $ttl,
        ]);
        return $data['result'];
    }

    public function updateDnsRecord(string $zoneId, string $recordId, string $type, string $name, string $content, bool $proxied = true, int $ttl = 1): array
    {
        $data = $this->call('PUT', '/zones/' . $zoneId . '/dns_records/' . $recordId, [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'proxied' => $proxied,
            'ttl' => $ttl,
        ]);
        return $data['result'];
    }

    public function deleteDnsRecord(string $zoneId, string $recordId): void
    {
        $this->call('DELETE', '/zones/' . $zoneId . '/dns_records/' . $recordId);
    }

    /**
     * Creates (or updates, if one already exists for the same hostname/type) the A record for the
     * apex domain and a CNAME for www pointing at the apex — matching the "domain.com IP" bulk push UI.
     */
    public function pushARecordWithWww(string $zoneId, string $domain, string $ip, bool $proxied = true): array
    {
        $existing = $this->listDnsRecords($zoneId);

        $aRecord = null;
        $wwwRecord = null;
        foreach ($existing as $record) {
            if ($record['type'] === 'A' && $record['name'] === $domain) {
                $aRecord = $record;
            }
            if (in_array($record['type'], ['CNAME', 'A'], true) && $record['name'] === 'www.' . $domain) {
                $wwwRecord = $record;
            }
        }

        $result = [];
        $result['a'] = $aRecord
            ? $this->updateDnsRecord($zoneId, $aRecord['id'], 'A', $domain, $ip, $proxied)
            : $this->createDnsRecord($zoneId, 'A', $domain, $ip, $proxied);

        $result['www'] = $wwwRecord
            ? $this->updateDnsRecord($zoneId, $wwwRecord['id'], 'CNAME', 'www.' . $domain, $domain, $proxied)
            : $this->createDnsRecord($zoneId, 'CNAME', 'www.' . $domain, $domain, $proxied);

        return $result;
    }

    public function deleteAllDnsRecords(string $zoneId): int
    {
        $records = $this->listDnsRecords($zoneId);
        foreach ($records as $record) {
            $this->deleteDnsRecord($zoneId, $record['id']);
        }
        return count($records);
    }

    public function listPageRules(string $zoneId): array
    {
        $data = $this->call('GET', '/zones/' . $zoneId . '/pagerules?status=active');
        return $data['result'] ?? [];
    }

    public function createRedirectPageRule(string $zoneId, string $sourcePattern, string $targetUrl, int $statusCode = 301): array
    {
        $data = $this->call('POST', '/zones/' . $zoneId . '/pagerules', [
            'targets' => [[
                'target' => 'url',
                'constraint' => ['operator' => 'matches', 'value' => $sourcePattern],
            ]],
            'actions' => [[
                'id' => 'forwarding_url',
                'value' => ['url' => $targetUrl, 'status_code' => $statusCode],
            ]],
            'status' => 'active',
        ]);
        return $data['result'];
    }

    public function deletePageRule(string $zoneId, string $pageRuleId): void
    {
        $this->call('DELETE', '/zones/' . $zoneId . '/pagerules/' . $pageRuleId);
    }

    public function deleteAllPageRules(string $zoneId): int
    {
        $rules = $this->listPageRules($zoneId);
        foreach ($rules as $rule) {
            $this->deletePageRule($zoneId, $rule['id']);
        }
        return count($rules);
    }
}

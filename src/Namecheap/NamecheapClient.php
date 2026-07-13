<?php

namespace App\Namecheap;

use App\Support\Http;

/**
 * Thin wrapper around Namecheap's XML API. Only the one call this tool needs
 * (setting custom nameservers on a domain) is implemented.
 *
 * Note: Namecheap requires the calling server's public IP to be whitelisted
 * under Profile > Tools > API Access in the Namecheap account first.
 */
class NamecheapClient
{
    private const BASE_URL = 'https://api.namecheap.com/xml.response';

    private string $userName;

    public function __construct(
        private string $apiUser,
        private string $apiKey,
        private string $clientIp,
        ?string $userName = null
    ) {
        $this->userName = $userName ?: $apiUser;
    }

    public function setCustomNameservers(string $domain, array $nameservers): void
    {
        [$sld, $tld] = $this->splitDomain($domain);

        $params = [
            'ApiUser' => $this->apiUser,
            'ApiKey' => $this->apiKey,
            'UserName' => $this->userName,
            'ClientIp' => $this->clientIp,
            'Command' => 'namecheap.domains.dns.setCustom',
            'SLD' => $sld,
            'TLD' => $tld,
            'Nameservers' => implode(',', $nameservers),
        ];

        $url = self::BASE_URL . '?' . http_build_query($params);
        $response = Http::request('GET', $url, [], null, 30);

        $xml = @simplexml_load_string($response['body']);
        if ($xml === false) {
            throw new NamecheapException('Không đọc được phản hồi từ Namecheap API.');
        }

        $status = (string) $xml['Status'];
        if ($status !== 'OK') {
            $messages = [];
            if (isset($xml->Errors->Error)) {
                foreach ($xml->Errors->Error as $err) {
                    $messages[] = (string) $err;
                }
            }
            throw new NamecheapException($messages ? implode('; ', $messages) : 'Namecheap API trả lỗi không xác định.');
        }
    }

    private function splitDomain(string $domain): array
    {
        $parts = explode('.', $domain, 2);
        if (count($parts) !== 2) {
            throw new NamecheapException("Domain không hợp lệ: {$domain}");
        }
        return $parts;
    }
}

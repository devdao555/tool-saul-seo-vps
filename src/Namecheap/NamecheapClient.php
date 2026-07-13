<?php

namespace App\Namecheap;

use App\Support\Http;

/**
 * Thin wrapper around Namecheap's XML API — only the handful of calls this tool needs.
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

        $this->call([
            'Command' => 'namecheap.domains.dns.setCustom',
            'SLD' => $sld,
            'TLD' => $tld,
            'Nameservers' => implode(',', $nameservers),
        ]);
    }

    /**
     * Returns the registrar expiry date (Y-m-d) for a domain registered at Namecheap,
     * or null if the domain isn't found under this account.
     */
    public function getDomainExpiry(string $domain): ?string
    {
        [$sld, $tld] = $this->splitDomain($domain);

        $xml = $this->call([
            'Command' => 'namecheap.domains.getInfo',
            'DomainName' => $sld . '.' . $tld,
        ]);

        $expiredDate = (string) ($xml->CommandResponse->DomainGetInfoResult->DomainDetails->ExpiredDate ?? '');
        if ($expiredDate === '') {
            return null;
        }

        $date = \DateTime::createFromFormat('m/d/Y', $expiredDate);
        return $date ? $date->format('Y-m-d') : null;
    }

    private function call(array $params): \SimpleXMLElement
    {
        $params = array_merge([
            'ApiUser' => $this->apiUser,
            'ApiKey' => $this->apiKey,
            'UserName' => $this->userName,
            'ClientIp' => $this->clientIp,
        ], $params);

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

        return $xml;
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

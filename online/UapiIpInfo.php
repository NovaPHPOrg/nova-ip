<?php

declare(strict_types=1);

namespace nova\plugin\ip\online;

use function nova\framework\config;

use nova\plugin\http\HttpClient;
use nova\plugin\ip\IpModel;

/**
 * UAPI 网络 IP 查询（uapis.cn）。
 *
 * @see https://uapis.cn/docs/api/network/ipinfo
 */
class UapiIpInfo extends AbstractOnlineIpProvider
{
    public const string SOURCE = 'uapis.cn';

    protected const int TIMEOUT = 10;

    private bool $commercial = false;

    public function source(): string
    {
        return self::SOURCE;
    }

    public function withCommercial(bool $commercial = true): self
    {
        $this->commercial = $commercial;

        return $this;
    }

    public function httpClient(): HttpClient
    {
        $client = parent::httpClient();
        $apiKey = trim((string)(config('uapi.api_key') ?? ''));
        if ($apiKey !== '') {
            $client->setHeader('Authorization', 'Bearer ' . $apiKey);
        }

        return $client;
    }

    public function requestUrl(string $ip): ?string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return null;
        }

        $params = ['ip' => $ip];
        if ($this->commercial) {
            $params['source'] = 'commercial';
        }

        return $this->baseUrl() . '/api/v1/network/ipinfo?' . http_build_query($params);
    }

    protected function baseUrl(): string
    {
        return 'https://uapis.cn';
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function parsePayload(array $payload): ?IpModel
    {
        return IpModel::fromUapi($payload);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function normalizePayload(array $data): ?array
    {
        if (!isset($data['ip'])) {
            return null;
        }

        return $data;
    }
}

<?php

declare(strict_types=1);

namespace nova\plugin\ip\online;

use nova\plugin\ip\IpModel;

/**
 * ip-api.com 在线 IP 地理定位。
 *
 * @see https://ip-api.com/docs/api:json
 */
class IpAPI extends AbstractOnlineIpProvider
{
    public const string SOURCE = 'ip-api.com';

    private const int FIELDS = 61439;

    public function source(): string
    {
        return self::SOURCE;
    }

    public function requestUrl(string $ip): ?string
    {
        return $this->buildRequestUrl($ip, 'zh-CN');
    }

    public function buildRequestUrl(string $ip, string $lang = 'zh-CN'): ?string
    {
        $ip = trim($ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $this->baseUrl() . '/json/' . rawurlencode($ip) . '?' . http_build_query([
                'fields' => (string)self::FIELDS,
                'lang' => $lang,
            ]);
    }

    protected function baseUrl(): string
    {
        return 'http://ip-api.com';
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function parsePayload(array $payload): ?IpModel
    {
        return IpModel::fromIpApi($payload);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function normalizePayload(array $data): ?array
    {
        if (($data['status'] ?? '') !== 'success') {
            return null;
        }

        return $data;
    }
}

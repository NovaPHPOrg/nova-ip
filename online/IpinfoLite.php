<?php

declare(strict_types=1);

namespace nova\plugin\ip\online;

use nova\plugin\ip\IpModel;
use function nova\framework\config;

/**
 * IPinfo Lite（免费、不限次数的国家/ASN 数据）。
 *
 * @see https://ipinfo.io/developers/lite-api
 */
class IpinfoLite extends AbstractOnlineIpProvider
{
    public const string SOURCE = 'ipinfo.io';

    private const string DEFAULT_TOKEN = 'f478f5d107dbaf';

    public function source(): string
    {
        return self::SOURCE;
    }

    public function requestUrl(string $ip): ?string
    {
        $ip = trim($ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $token = trim((string)(config('ipinfo.token') ?? self::DEFAULT_TOKEN));
        if ($token === '') {
            return null;
        }

        return $this->baseUrl() . '/lite/' . rawurlencode($ip) . '?' . http_build_query([
                'token' => $token,
            ]);
    }

    protected function baseUrl(): string
    {
        return 'https://api.ipinfo.io';
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function parsePayload(array $payload): ?IpModel
    {
        return IpModel::fromIpinfo($payload);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function normalizePayload(array $data): ?array
    {
        if (!isset($data['ip']) || isset($data['error']) || isset($data['bogon'])) {
            return null;
        }

        return $data;
    }
}

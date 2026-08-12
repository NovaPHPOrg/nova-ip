<?php

declare(strict_types=1);

namespace nova\plugin\ip\online;

use nova\plugin\ip\IpModel;
use function nova\framework\config;

/**
 * IP2Location.io 在线 IP 地理定位。
 *
 * 无 key：1000 次/天；注册 Free Plan 后配置 api_key 可至 5 万次/月。
 *
 * @see https://www.ip2location.io/ip2location-documentation
 */
class Ip2LocationIo extends AbstractOnlineIpProvider
{
    public const string SOURCE = 'ip2location.io';

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

        $params = ['ip' => $ip];
        $apiKey = trim((string)(config('ip2location_io.api_key') ?? ''));
        if ($apiKey !== '') {
            $params['key'] = $apiKey;
        }

        return $this->baseUrl() . '/?' . http_build_query($params);
    }

    protected function baseUrl(): string
    {
        return 'https://api.ip2location.io';
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function parsePayload(array $payload): ?IpModel
    {
        return IpModel::fromIp2LocationIo($payload);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function normalizePayload(array $data): ?array
    {
        if (!isset($data['ip'])) {
            return null;
        }

        if (isset($data['error']) || isset($data['error_code'])) {
            return null;
        }

        $country = trim((string)($data['country_name'] ?? ''));
        $region = trim((string)($data['region_name'] ?? ''));
        $city = trim((string)($data['city_name'] ?? ''));

        if ($country === '' && $region === '' && $city === '') {
            return null;
        }

        return $data;
    }
}

<?php

declare(strict_types=1);

namespace nova\plugin\ip\online;

use nova\plugin\ip\IpModel;

/**
 * 小小 API IP 查询（v2.xxapi.cn）。
 *
 * @see https://v2.xxapi.cn/api/ip
 */
class XxapiIp extends AbstractOnlineIpProvider
{
    public const string SOURCE = 'xxapi.cn';

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

        return $this->baseUrl() . '/api/ip?' . http_build_query(['ip' => $ip]);
    }

    protected function baseUrl(): string
    {
        return 'https://v2.xxapi.cn';
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function parsePayload(array $payload): ?IpModel
    {
        return IpModel::fromXxapi($payload);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function normalizePayload(array $data): ?array
    {
        if ((int)($data['code'] ?? 0) !== 200) {
            return null;
        }

        return $data;
    }
}

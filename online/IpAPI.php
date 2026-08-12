<?php

declare(strict_types=1);

namespace nova\plugin\ip\online;

use nova\framework\core\Logger;
use nova\plugin\http\HttpResponse;
use nova\plugin\ip\IpModel;
use Throwable;

/**
 * ip-api.com 在线 IP 地理定位。
 *
 * 免费限制（官方文档）：
 * - 45 次/分钟/IP，超出返回 HTTP 429，持续超限封禁 1 小时
 * - 仅 HTTP，禁止商用
 *
 * @see https://ip-api.com/docs/api:json
 */
class IpAPI extends AbstractOnlineIpProvider
{
    public const string SOURCE = 'ip-api.com';

    private const int FIELDS = 61439;

    /** 官方免费额度：45 次/分钟 */
    private const int RATE_LIMIT_PER_MINUTE = 45;

    private static float $rateWindowStart = 0.0;

    private static int $rateWindowCount = 0;

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

    public function fromIp(string $ip): ?IpModel
    {
        $ip = trim($ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $url = $this->requestUrl($ip);
        if ($url === null) {
            return null;
        }

        $cached = $this->readCache($url);
        if ($cached !== null) {
            return $this->fromResponse($cached);
        }

        $this->waitForRateLimit();

        try {
            $response = $this->httpClient()->send($url);
            if ($response !== null) {
                $this->writeCache($url, $response);
            }

            return $this->fromResponse($response);
        } catch (Throwable $e) {
            Logger::warning('[IP-Online] ' . $this->source() . ': ' . $e->getMessage());

            return null;
        }
    }

    public function fromResponse(?HttpResponse $response): ?IpModel
    {
        if ($response === null) {
            return null;
        }

        $code = $response->getHttpCode();
        if ($code === 429) {
            $ttl = (int)($response->getHeaders()['X-Ttl'] ?? $response->getHeaders()['x-ttl'] ?? 60);
            Logger::warning('[IP-Online] ip-api.com 触发限流，等待 ' . max(1, $ttl) . ' 秒');
            sleep(max(1, $ttl));

            return null;
        }

        return parent::fromResponse($response);
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

    private function waitForRateLimit(): void
    {
        $now = microtime(true);
        if (self::$rateWindowStart <= 0 || ($now - self::$rateWindowStart) >= 60) {
            self::$rateWindowStart = $now;
            self::$rateWindowCount = 0;
        }

        if (self::$rateWindowCount >= self::RATE_LIMIT_PER_MINUTE) {
            $sleep = 60 - ($now - self::$rateWindowStart);
            if ($sleep > 0) {
                usleep((int)ceil($sleep * 1_000_000));
            }
            self::$rateWindowStart = microtime(true);
            self::$rateWindowCount = 0;
        }

        self::$rateWindowCount++;
    }
}

<?php

declare(strict_types=1);

namespace nova\plugin\ip\online;

use nova\framework\core\Context;
use nova\framework\core\Instance;
use nova\framework\core\Logger;
use nova\plugin\http\HttpClient;
use nova\plugin\http\HttpException;
use nova\plugin\http\HttpResponse;
use nova\plugin\ip\IpModel;
use Throwable;

abstract class AbstractOnlineIpProvider extends Instance implements OnlineIpProvider
{
    protected const int TIMEOUT = 8;

    protected const int CACHE_SECONDS = 86400;

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

        try {
            $response = $this->httpClient()->send($url);
            if ($response !== null) {
                $this->writeCache($url, $response);
            }

            return $this->fromResponse($response);
        } catch (HttpException|Throwable $e) {
            Logger::warning('[IP-Online] ' . $this->source() . ': ' . $e->getMessage());

            return null;
        }
    }

    public function readCache(string $url): ?HttpResponse
    {
        if ($this->cacheSeconds() <= 0) {
            return null;
        }

        $cached = Context::instance()->cache->get(md5($url));

        return $cached instanceof HttpResponse ? $cached : null;
    }

    protected function cacheSeconds(): int
    {
        return static::CACHE_SECONDS;
    }

    public function fromResponse(?HttpResponse $response): ?IpModel
    {
        if ($response === null) {
            return null;
        }

        $code = $response->getHttpCode();
        if ($code < 200 || $code >= 300) {
            return null;
        }

        $data = json_decode($response->getBody(), true);
        if (!is_array($data)) {
            return null;
        }

        $payload = $this->normalizePayload($data);
        if ($payload === null) {
            return null;
        }

        $model = $this->parsePayload($payload);
        if ($model === null || $model->isEmpty()) {
            return null;
        }

        $model->source = $this->source();

        return $model;
    }

    /**
     * @param  array<string, mixed>      $data
     * @return array<string, mixed>|null
     */
    protected function normalizePayload(array $data): ?array
    {
        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     */
    abstract protected function parsePayload(array $payload): ?IpModel;

    public function httpClient(): HttpClient
    {
        return HttpClient::init($this->baseUrl())
            ->get()
            ->timeout($this->timeout())
            ->cache($this->cacheSeconds())
            ->autoProxy();
    }

    protected function timeout(): int
    {
        return static::TIMEOUT;
    }

    abstract protected function baseUrl(): string;

    public function writeCache(string $url, HttpResponse $response): void
    {
        if ($this->cacheSeconds() <= 0) {
            return;
        }

        Context::instance()->cache->set(md5($url), $response, $this->cacheSeconds());
    }
}

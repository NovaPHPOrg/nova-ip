<?php

declare(strict_types=1);

namespace nova\plugin\ip\online;

use nova\plugin\http\HttpClient;
use nova\plugin\http\HttpResponse;
use nova\plugin\ip\IpModel;

interface OnlineIpProvider
{
    public function source(): string;

    public function requestUrl(string $ip): ?string;

    public function httpClient(): HttpClient;

    public function fromIp(string $ip): ?IpModel;

    public function fromResponse(?HttpResponse $response): ?IpModel;

    public function readCache(string $url): ?HttpResponse;

    public function writeCache(string $url, HttpResponse $response): void;
}

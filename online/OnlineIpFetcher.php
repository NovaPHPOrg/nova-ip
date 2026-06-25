<?php

declare(strict_types=1);

namespace nova\plugin\ip\online;

use nova\framework\core\Instance;
use nova\plugin\http\MultiHttp;
use nova\plugin\ip\IpModel;

/**
 * 并发拉取多个在线 IP 数据源，结果顺序与 provider 注册顺序一致。
 */
final class OnlineIpFetcher extends Instance
{
    /**
     * @return list<?IpModel> 固定 4 项：ip-api / ipinfo / xxapi / uapis
     */
    public function fetchOrdered(string $ip, bool $uapiCommercial = false): array
    {
        $ip = trim($ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return [null, null, null, null];
        }

        $uapi = UapiIpInfo::getInstance()->withCommercial($uapiCommercial);

        $providers = [
            IpAPI::getInstance(),
            IpinfoLite::getInstance(),
            XxapiIp::getInstance(),
            $uapi,
        ];

        $models = array_fill(0, count($providers), null);
        $pending = [];

        foreach ($providers as $index => $provider) {
            $url = $provider->requestUrl($ip);
            if ($url === null) {
                continue;
            }

            $cached = $provider->readCache($url);
            if ($cached !== null) {
                $models[$index] = $provider->fromResponse($cached);
                continue;
            }

            $pending[] = [
                'url' => $url,
                'client' => $provider->httpClient(),
                'provider' => $provider,
                'index' => $index,
            ];
        }

        if ($pending !== []) {
            MultiHttp::runRequests(
                $pending,
                count($pending),
                function (string $url, $response, $provider, int $index) use (&$models): void {
                    if (!$provider instanceof OnlineIpProvider) {
                        return;
                    }
                    $provider->writeCache($url, $response);
                    $models[$index] = $provider->fromResponse($response);
                },
            );
        }

        $uapi->withCommercial(false);

        return $models;
    }
}

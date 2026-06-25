<?php

declare(strict_types=1);

namespace nova\plugin\ip;

use nova\framework\core\Instance;
use nova\plugin\ip\online\OnlineIpFetcher;
use function nova\framework\config;

/**
 * IP 定位入口：默认本地 ip2region，可选叠加在线 API 补充 org/as 等。
 */
final class IpLocation extends Instance
{
    /**
     * @param bool $multiSource true 时合并在线 API 与本地库（在线在前，本地最后兜底）
     */
    public function fromIp(string $ip, bool $multiSource = false): ?IpModel
    {
        $ip = trim($ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $local = $this->fromLocalDb($ip);
        if (!$multiSource) {
            return $local;
        }

        $commercial = (bool)(config('uapi.commercial') ?? false);
        [$ipApi, $ipinfo, $xxapi, $uapi] = OnlineIpFetcher::getInstance()->fetchOrdered($ip, $commercial);

        return IpModel::merge($ipApi, $ipinfo, $xxapi, $uapi, $local);
    }

    private function fromLocalDb(string $ip): ?IpModel
    {
        $info = Ip2Region::getInstance()->getIpInfo($ip);
        if ($info === null) {
            return null;
        }

        $model = IpModel::fromIp2Region($info);
        if ($model->isEmpty()) {
            return null;
        }
        $model->source = 'ip2region';

        return $model;
    }
}

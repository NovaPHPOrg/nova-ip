<?php

declare(strict_types=1);

namespace nova\plugin\ip;

use nova\framework\core\Instance;
use nova\plugin\ip\online\Ip2LocationIo;

/**
 * IP 定位入口：默认本地 ip2region；混合模式合并 ip2location.io 与本地库（在线在前、本地兜底）。
 */
final class IpLocation extends Instance
{
    /**
     * @param bool $multiSource true 时混合 ip2location.io + ip2region
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

        $online = Ip2LocationIo::getInstance()->fromIp($ip);
        $merged = IpModel::merge($online, $local);

        return $merged ?? $online ?? $local;
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

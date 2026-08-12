<?php

declare(strict_types=1);

namespace nova\plugin\ip;

use nova\framework\core\Instance;
use nova\plugin\ip\online\Ip2LocationIo;

/**
 * IP 定位入口：默认在线 ip2location.io，失败回退 ip2region；英文结果经 Edge 翻译为中文。
 */
final class IpLocation extends Instance
{
    /**
     * 在线优先，失败则本地。
     */
    public function fromIp(string $ip): ?IpModel
    {
        return $this->fromOnline($ip);
    }

    /**
     * ip2location.io；无结果或空结果时回退 ip2region。
     */
    public function fromOnline(string $ip): ?IpModel
    {
        $ip = trim($ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $model = Ip2LocationIo::getInstance()->fromIp($ip);
        if ($model === null || $model->isEmpty()) {
            $model = $this->fromLocalDb($ip);
        }

        return GeoTranslator::apply($model);
    }

    /**
     * 仅本地 ip2region。
     */
    public function fromLocal(string $ip): ?IpModel
    {
        $ip = trim($ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return GeoTranslator::apply($this->fromLocalDb($ip));
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

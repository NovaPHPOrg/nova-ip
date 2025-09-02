<?php

declare(strict_types=1);

namespace nova\plugin\ip\IpParser;

interface IpParserInterface
{
    public function setDBPath($filePath);

    /**
     * @param        $ip
     * @return mixed ['ip', 'country', 'area']
     */
    public function getIp($ip);
}

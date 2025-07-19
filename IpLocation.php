<?php

declare(strict_types=1);
/*
 * Copyright (c) 2023. Ankio. All Rights Reserved.
 */

namespace nova\plugin\ip;

use nova\plugin\ip\IpParser\IpV6wry;
use nova\plugin\ip\IpParser\QWry;

/**
 * IP数据库根目录常量定义
 * 用于指定IP数据库文件的存储路径
 */
define("IP_DATABASE_ROOT_DIR", __DIR__);

/**
 * IP地理位置查询类
 *
 * 该类提供了IP地址地理位置查询功能，支持IPv4和IPv6地址。
 * 使用纯真IP数据库(qqwry.dat)和IPv6数据库(ipv6wry.db)进行地理位置查询。
 *
 * @package nova\plugin\ip
 * @author Ankio
 * @since 2023
 */
class IpLocation
{
    /**
     * IPv4数据库文件路径
     *
     * @var string|null
     */
    private static $ipV4Path;

    /**
     * IPv6数据库文件路径
     *
     * @var string|null
     */
    private static $ipV6Path;

    /**
     * 获取IP地址的地理位置信息（已解析）
     *
     * 该方法会根据IP地址类型自动选择合适的数据库进行查询，
     * 并返回解析后的地理位置信息。
     *
     * @param  string $ip       要查询的IP地址
     * @param  string $ipV4Path IPv4数据库文件路径，为空时使用默认路径
     * @param  string $ipV6Path IPv6数据库文件路径，为空时使用默认路径
     * @return array  返回地理位置信息数组，包含国家、省份、城市等信息
     *                如果查询失败，返回包含error键的数组
     */
    public static function getLocation($ip, string $ipV4Path = '', string $ipV6Path = ''): array
    {
        $location = self::getLocationWithoutParse($ip, $ipV4Path, $ipV6Path);
        if (isset($location['error'])) {
            return $location;
        }
        return StringParser::parse($location);
    }

    /**
     * 获取IP地址的地理位置信息（未解析）
     *
     * 该方法会根据IP地址类型自动选择合适的数据库进行查询，
     * 返回原始的地理位置信息，不进行字符串解析。
     *
     * @param  string $ip       要查询的IP地址
     * @param  string $ipV4Path IPv4数据库文件路径，为空时使用默认路径
     * @param  string $ipV6Path IPv6数据库文件路径，为空时使用默认路径
     * @return array  返回原始地理位置信息数组
     *                如果IP地址无效，返回包含error键的数组
     */
    public static function getLocationWithoutParse($ip, string $ipV4Path = '', string $ipV6Path = ''): array
    {

        // 如果提供了IPv4数据库路径，则设置IPv4数据库路径
        if (strlen($ipV4Path)) {
            self::setIpV4Path($ipV4Path);
        }

        // 如果提供了IPv6数据库路径，则设置IPv6数据库路径
        if (strlen($ipV6Path)) {
            self::setIpV6Path($ipV6Path);
        }

        if (self::isIpV4($ip)) {
            // IPv4地址查询
            $ins = new QWry();
            $ins->setDBPath(self::getIpV4Path());
            $location = $ins->getIp($ip);
        } elseif (self::isIpV6($ip)) {
            // IPv6地址查询
            $ins = new IpV6wry();
            $ins->setDBPath(self::getIpV6Path());
            $location = $ins->getIp($ip);

        } else {
            // IP地址格式无效
            $location = [
                'error' => 'IP Invalid'
            ];
        }

        return $location;
    }

    /**
     * 设置IPv4数据库文件路径
     *
     * @param string $path IPv4数据库文件的完整路径
     */
    public static function setIpV4Path($path)
    {
        self::$ipV4Path = $path;
    }

    /**
     * 设置IPv6数据库文件路径
     *
     * @param string $path IPv6数据库文件的完整路径
     */
    public static function setIpV6Path($path)
    {
        self::$ipV6Path = $path;
    }

    /**
     * 验证是否为有效的IPv4地址
     *
     * @param  string $ip 要验证的IP地址
     * @return bool   如果是有效的IPv4地址返回true，否则返回false
     */
    private static function isIpV4($ip): bool
    {
        return false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    /**
     * 获取IPv4数据库文件路径
     *
     * 如果未设置自定义路径，则使用默认的qqwry.dat文件
     *
     * @return string IPv4数据库文件的完整路径
     */
    private static function getIpV4Path(): string
    {
        return self::$ipV4Path ?: self::root('/db/qqwry.dat');
    }

    /**
     * 获取数据库文件的完整路径
     *
     * 将相对路径与IP数据库根目录拼接，生成完整的文件路径
     *
     * @param  string $filename 相对于数据库根目录的文件名
     * @return string 完整的文件路径
     */
    public static function root($filename): string
    {
        return IP_DATABASE_ROOT_DIR . $filename;
    }

    /**
     * 验证是否为有效的IPv6地址
     *
     * @param  string $ip 要验证的IP地址
     * @return bool   如果是有效的IPv6地址返回true，否则返回false
     */
    private static function isIpV6($ip): bool
    {
        return false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    /**
     * 获取IPv6数据库文件路径
     *
     * 如果未设置自定义路径，则使用默认的ipv6wry.db文件
     *
     * @return string IPv6数据库文件的完整路径
     */
    private static function getIpV6Path(): string
    {
        return self::$ipV6Path ?: self::root('/db/ipv6wry.db');
    }
}

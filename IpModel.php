<?php

declare(strict_types=1);

namespace nova\plugin\ip;

use nova\framework\core\ArgObject;

/**
 * IP 地理与网络归属信息。
 *
 * location 为国家/省/市拼接（` / ` 分隔）；org、as 通常需在线 API 补充。
 */
class IpModel extends ArgObject
{
    /** 单源 location 内部拼接符（国家/省/市） */
    private const string LOCATION_GLUE = ' ';

    /** merge 多源之间的拼接符 */
    private const string MERGE_GLUE = ' / ';
    /** @var string 地理定位（国家 / 省 / 市） */
    public string $location = '';

    /** @var string 数据来源（merge 时写入括号标记） */
    public string $source = '';

    /** @var string 运营商 */
    public string $isp = '';

    /** @var string 组织 */
    public string $org = '';

    /** @var string 自治系统（AS） */
    public string $as = '';

    /**
     * {@see Ip2Region::getIpInfo()} 返回值
     *
     * @param array<string, mixed> $info
     */
    public static function fromIp2Region(array $info): self
    {
        return new self([
            'location' => self::buildLocation(
                (string)($info['country'] ?? ''),
                (string)($info['province'] ?? $info['region'] ?? ''),
                (string)($info['city'] ?? ''),
            ),
            'isp' => (string)($info['isp'] ?? ''),
        ]);
    }

    private static function buildLocation(string ...$parts): string
    {
        $parts = array_values(array_filter(
            array_map(static fn (string $part): string => trim($part), $parts),
            static fn (string $part): bool => $part !== '' && $part !== '0',
        ));

        return implode(self::LOCATION_GLUE, $parts);
    }

    /**
     * ip-api.com 等在线接口 JSON
     *
     * @param array<string, mixed> $payload
     */
    public static function fromIpApi(array $payload): self
    {
        return new self([
            'location' => self::buildLocation(
                (string)($payload['country'] ?? ''),
                (string)($payload['regionName'] ?? $payload['region'] ?? ''),
                (string)($payload['city'] ?? ''),
            ),
            'isp' => (string)($payload['isp'] ?? ''),
            'org' => (string)($payload['org'] ?? ''),
            'as' => (string)($payload['as'] ?? ''),
        ]);
    }

    /**
     * uapis.cn /api/v1/network/ipinfo
     *
     * @param array<string, mixed> $payload
     */
    public static function fromUapi(array $payload): self
    {
        $country = trim((string)($payload['country'] ?? ''));
        $region = trim((string)($payload['province'] ?? $payload['state'] ?? ''));
        $city = trim((string)($payload['city'] ?? ''));

        if ($country === '' && isset($payload['region']) && is_string($payload['region'])) {
            $parts = preg_split('/\s+/u', trim($payload['region']), 3) ?: [];
            $country = trim((string)($parts[0] ?? ''));
            if ($region === '') {
                $region = trim((string)($parts[1] ?? ''));
            }
            if ($city === '') {
                $city = trim((string)($parts[2] ?? ''));
            }
        }

        return new self([
            'location' => self::buildLocation($country, $region, $city),
            'isp' => (string)($payload['isp'] ?? ''),
            'org' => (string)($payload['llc'] ?? $payload['org'] ?? ''),
            'as' => (string)($payload['asn'] ?? $payload['as'] ?? ''),
        ]);
    }

    /**
     * v2.xxapi.cn /api/ip（data.address 如「中国浙江温州 电信」）
     *
     * @param array<string, mixed> $payload
     */
    public static function fromXxapi(array $payload): self
    {
        $data = $payload['data'] ?? $payload;
        if (!is_array($data)) {
            return new self();
        }

        $address = trim((string)($data['address'] ?? ''));
        if ($address === '') {
            return new self();
        }

        return new self(self::parseXxapiAddress($address));
    }

    /**
     * @return array{location: string, isp: string}
     */
    private static function parseXxapiAddress(string $address): array
    {
        // 「末段为运营商」的格式仅对国内数据成立（如「中国浙江温州 电信」）；
        // 境外地址整串作为 location，不猜运营商，避免把地名末段误当 ISP。
        if (!str_starts_with($address, '中国')) {
            return ['location' => $address, 'isp' => ''];
        }

        $location = $address;
        $isp = '';
        if (preg_match('/^(.+?)\s+(\S+)$/u', $address, $m)) {
            $location = trim($m[1]);
            $isp = trim($m[2]);
        }

        $region = '';
        $city = '';
        $rest = mb_substr($location, 2);
        if ($rest !== '') {
            if (preg_match('/^(.+?(?:省|自治区|特别行政区))(.+)?$/u', $rest, $m)) {
                $region = trim($m[1]);
                $city = trim((string)($m[2] ?? ''));
            } elseif (preg_match('/^(.{2,3}?)(.{2,})$/u', $rest, $m)) {
                $region = $m[1];
                $city = $m[2];
            } else {
                $region = $rest;
            }
        }

        return [
            'location' => self::buildLocation('中国', $region, $city),
            'isp' => $isp,
        ];
    }

    /**
     * IPinfo Lite（api.ipinfo.io/lite/{ip}）
     *
     * @param array<string, mixed> $payload
     */
    public static function fromIpinfo(array $payload): self
    {
        $asName = trim((string)($payload['as_name'] ?? ''));

        // IPinfo Lite 的 country 是英文名（如 "China"），与其它中文源混排会污染 location；
        // 该源核心价值是 ASN/org，地理交给本地库与中文在线源。
        return new self([
            'isp' => $asName,
            'org' => $asName,
            'as' => (string)($payload['asn'] ?? ''),
        ]);
    }

    /**
     * 合并多源结果：各字段按传入顺序用 ` / ` 拼接，去重；在线 API 在前，本地 ip2region 放最后。
     *
     * @param self|null ...$models
     */
    public static function merge(?self ...$models): ?self
    {
        $models = array_values(array_filter($models));
        if ($models === []) {
            return null;
        }

        $merged = new self([
            'location' => self::collectGeo($models, 'location'),
            'isp' => self::collectText($models, 'isp'),
            'org' => self::collectText($models, 'org'),
            'as' => self::mergeAsField($models),
        ]);

        return $merged->isEmpty() ? null : $merged;
    }

    /**
     * 地理字段：按归一化 key 合并，互为包含时保留最精确（更长）的写法。
     * key 为空（无法归一化）的值原样保留，且不参与包含判断，避免空串恒匹配。
     *
     * @param list<self> $models
     */
    private static function collectGeo(array $models, string $field): string
    {
        /** @var list<array{key: string, text: string}> $picked */
        $picked = [];

        foreach ($models as $model) {
            $val = trim($model->$field);
            if ($val === '') {
                continue;
            }

            $key = self::geoKey($val);
            $text = self::labelSource($val, $model->source);

            if ($key === '') {
                $picked[] = ['key' => '', 'text' => $text];
                continue;
            }

            $skip = false;
            foreach ($picked as $i => $entry) {
                if ($entry['key'] === '') {
                    continue;
                }
                // 已有的更精确或相等：丢弃当前
                if (str_contains($entry['key'], $key)) {
                    $skip = true;
                    break;
                }
                // 当前更精确：替换已有
                if (str_contains($key, $entry['key'])) {
                    $picked[$i] = ['key' => $key, 'text' => $text];
                    $skip = true;
                    break;
                }
            }

            if (!$skip) {
                $picked[] = ['key' => $key, 'text' => $text];
            }
        }

        return implode(self::MERGE_GLUE, array_map(
            static fn (array $entry): string => $entry['text'],
            $picked,
        ));
    }

    private static function geoKey(string $value): string
    {
        $key = mb_strtolower(trim($value));
        if ($key === '') {
            return '';
        }

        $key = preg_replace('/(省|市|自治区|特别行政区|壮族|回族|维吾尔)$/u', '', $key) ?? $key;

        return str_replace(' ', '', $key);
    }

    private static function labelSource(string $value, string $source): string
    {
        $source = trim($source);

        return $source === '' ? $value : "{$value} ({$source})";
    }

    /**
     * 文本字段（isp/org）：按小写值去重，保留首次出现，并带来源标签。
     *
     * @param list<self> $models
     */
    private static function collectText(array $models, string $field): string
    {
        $parts = [];
        $seen = [];
        foreach ($models as $model) {
            $val = trim($model->$field);
            if ($val === '') {
                continue;
            }
            $key = mb_strtolower($val);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $parts[] = self::labelSource($val, $model->source);
        }

        return implode(self::MERGE_GLUE, $parts);
    }

    /**
     * @param list<self> $models
     */
    private static function mergeAsField(array $models): string
    {
        $parts = [];
        $seen = [];
        foreach ($models as $model) {
            $val = trim($model->as);
            if ($val === '') {
                continue;
            }
            $key = self::asnKey($val);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $parts[] = self::labelSource($val, $model->source);
        }

        return implode(self::MERGE_GLUE, $parts);
    }

    private static function asnKey(string $value): string
    {
        if (preg_match('/\b(as\d+)\b/i', $value, $m)) {
            return strtolower($m[1]);
        }

        return mb_strtolower(trim($value));
    }

    public function isEmpty(): bool
    {
        return $this->location === ''
            && $this->isp === ''
            && $this->org === ''
            && $this->as === '';
    }

    public function onParseType(string $key, mixed &$val, mixed $demo): bool
    {
        if (is_string($val)) {
            $val = self::normalizeSegment(trim($val));
        }

        return parent::onParseType($key, $val, $demo);
    }

    private static function normalizeSegment(string $value): string
    {
        if ($value === '' || $value === '0' || $value === '内网IP') {
            return '';
        }

        return $value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        $parts = array_values(array_filter(
            [$this->location, $this->isp, $this->org, $this->as],
            static fn (string $v): bool => $v !== '',
        ));

        return implode('|', $parts);
    }
}

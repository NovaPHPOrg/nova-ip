<?php

declare(strict_types=1);

namespace nova\plugin\ip\translate;

use nova\framework\core\Context;
use nova\framework\core\Logger;
use nova\plugin\http\HttpClient;
use nova\plugin\http\HttpException;
use Throwable;

/**
 * Microsoft Edge 翻译（免鉴权），结果永久写入 cache（无 TTL）。
 *
 * @see https://www.ankio.net/research/technology/microsoft-edge-translate-api
 */
final class EdgeTranslate
{
    private const string BASE_URL = 'https://edge.microsoft.com';

    private const string PATH = '/translate/translatetext';

    private const string CACHE_PREFIX = 'edge_translate/';

    private const string DEFAULT_TO = 'zh-Hans';

    private const int BATCH_SIZE = 50;

    private const int TIMEOUT = 10;

    public static function translate(string $text): string
    {
        $text = trim($text);
        if ($text === '' || self::containsCjk($text)) {
            return $text;
        }

        $results = self::translateBatch([$text]);

        return $results[0] ?? $text;
    }

    private static function containsCjk(string $text): bool
    {
        return preg_match('/\p{Han}/u', $text) === 1;
    }

    /**
     * @param list<string> $texts
     * @return list<string> 与输入顺序一致
     */
    public static function translateBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $to = self::DEFAULT_TO;
        $out = [];
        $pending = [];

        foreach ($texts as $i => $text) {
            $text = trim($text);
            if ($text === '' || self::containsCjk($text)) {
                $out[$i] = $text;
                continue;
            }

            $cacheKey = self::cacheKey($text, $to);
            $cached = Context::instance()->cache->get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                $out[$i] = $cached;
                continue;
            }

            $pending[$i] = $text;
        }

        if ($pending !== []) {
            foreach (array_chunk($pending, self::BATCH_SIZE, true) as $chunk) {
                $translated = self::requestBatch(array_values($chunk), $to);
                $idx = 0;
                foreach ($chunk as $i => $original) {
                    $value = trim($translated[$idx] ?? '');
                    $idx++;
                    if ($value === '') {
                        $value = $original;
                    }
                    $out[$i] = $value;
                    Context::instance()->cache->set(
                        self::cacheKey($original, $to),
                        $value,
                    );
                }
            }
        }

        ksort($out);

        return array_values($out);
    }

    private static function cacheKey(string $text, string $to): string
    {
        return self::CACHE_PREFIX . md5(mb_strtolower($text) . '|' . $to);
    }

    /**
     * @param list<string> $texts
     * @return list<string>
     */
    private static function requestBatch(array $texts, string $to): array
    {
        if ($texts === []) {
            return [];
        }

        try {
            $response = HttpClient::init(self::BASE_URL)
                ->post($texts, 'json')
                ->timeout(self::TIMEOUT)
                ->autoProxy()
                ->send(self::PATH, [
                    'from' => '',
                    'to' => $to,
                    'isEnterpriseClient' => 'false',
                ]);

            if ($response === null) {
                return $texts;
            }

            $code = $response->getHttpCode();
            if ($code < 200 || $code >= 300) {
                Logger::warning('[EdgeTranslate] HTTP ' . $code);

                return $texts;
            }

            $data = json_decode($response->getBody(), true);
            if (!is_array($data)) {
                return $texts;
            }

            $results = [];
            foreach ($data as $i => $item) {
                if (!is_array($item)) {
                    $results[] = $texts[$i] ?? '';
                    continue;
                }
                $translations = $item['translations'] ?? [];
                $text = is_array($translations[0] ?? null)
                    ? trim((string)(($translations[0]['text'] ?? '')))
                    : '';
                $results[] = $text !== '' ? $text : ($texts[$i] ?? '');
            }

            return $results;
        } catch (HttpException|Throwable $e) {
            Logger::warning('[EdgeTranslate] ' . $e->getMessage());

            return $texts;
        }
    }
}

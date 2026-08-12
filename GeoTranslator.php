<?php

declare(strict_types=1);

namespace nova\plugin\ip;

use nova\plugin\ip\translate\EdgeTranslate;

/**
 * IP 地理文本英文 → 中文（Edge 翻译 + 永久 cache）；已是中文则跳过。
 */
final class GeoTranslator
{

    public static function apply(?IpModel $model): ?IpModel
    {
        if ($model === null) {
            return null;
        }

        $parts = array_values(array_filter([
            trim($model->country),
            trim($model->region),
            trim($model->city),
        ], static fn(string $part): bool => $part !== ''));

        if ($parts === []) {
            $location = trim($model->location);
            if ($location !== '') {
                $model->location = EdgeTranslate::translate($location);
            }
        } else {
            $translated = EdgeTranslate::translateBatch($parts);
            $model->location = implode(' ', array_values(array_filter(
                $translated,
                static fn(string $part): bool => trim($part) !== '',
            )));
        }

        $isp = trim($model->isp);
        if ($isp !== '') {
            $model->isp = EdgeTranslate::translate($isp);
        }

        return $model;
    }
}

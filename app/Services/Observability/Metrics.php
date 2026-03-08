<?php

namespace App\Services\Observability;

use Illuminate\Support\Facades\Cache;

class Metrics
{
    private static array $keys = [
        'webhooks_total',
        'duplicates_total',
        'flows_executed_total',
        'messages_sent_total',
        'errors_total',
    ];

    public static function increment(string $key, int $by = 1): void
    {
        if (!in_array($key, self::$keys, true)) {
            self::$keys[] = $key;
        }
        Cache::increment(self::cacheKey($key), $by);
    }

    public static function all(): array
    {
        $data = [];
        foreach (self::$keys as $key) {
            $data[$key] = Cache::get(self::cacheKey($key), 0);
        }
        return $data;
    }

    private static function cacheKey(string $key): string
    {
        return 'metrics:' . $key;
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Production safety net for Laravel Cloud / container hosts where CACHE_STORE
 * is set to redis but Redis is missing, misconfigured, or temporarily down.
 *
 * A dead cache store turns Filament/Livewire into HTTP 500 (boot) and 419
 * (CSRF/session token cannot be persisted across poll requests).
 */
final class CacheStoreFailover
{
    public static function ensureReachable(): void
    {
        $default = (string) config('cache.default', 'database');

        if (! in_array($default, ['redis', 'memcached', 'dynamodb'], true)) {
            return;
        }

        if (self::probe($default)) {
            return;
        }

        $fallback = self::firstWorkingStore(['database', 'file', 'array'], exclude: $default);

        if ($fallback === null) {
            Log::error('Cache store unreachable and no fallback store is available.', [
                'configured' => $default,
            ]);

            return;
        }

        config([
            'cache.default' => $fallback,
            'game_analytics.cache_store' => $fallback,
            'security_platform.cache_store' => $fallback,
            'permission.cache.store' => $fallback,
        ]);

        Log::warning('Cache store unreachable; falling back for this process.', [
            'configured' => $default,
            'fallback' => $fallback,
        ]);
    }

    /**
     * @param  list<string>  $candidates
     */
    private static function firstWorkingStore(array $candidates, string $exclude): ?string
    {
        foreach ($candidates as $store) {
            if ($store === $exclude) {
                continue;
            }

            if (! array_key_exists($store, (array) config('cache.stores', []))) {
                continue;
            }

            if (self::probe($store)) {
                return $store;
            }
        }

        return null;
    }

    private static function probe(string $store): bool
    {
        try {
            $key = '__civin_cache_probe_'.bin2hex(random_bytes(4));
            Cache::store($store)->put($key, 1, 5);
            $ok = Cache::store($store)->pull($key) === 1;

            return $ok;
        } catch (Throwable) {
            return false;
        }
    }
}

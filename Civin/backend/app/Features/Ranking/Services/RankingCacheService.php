<?php

namespace App\Features\Ranking\Services;

use App\Features\Ranking\DTOs\RankingQueryData;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

final readonly class RankingCacheService
{
    private const TTL_SECONDS = 300;

    private const KEY_PREFIX = 'rankings';

    public function key(RankingQueryData $query): string
    {
        $country = $query->country === null || $query->country === ''
            ? 'all'
            : strtolower(trim($query->country));

        return implode(':', [
            self::KEY_PREFIX,
            $query->type,
            $query->period,
            'v'.$this->version($query->type, $query->period),
            $country,
            (string) $query->limit,
        ]);
    }

    public function get(RankingQueryData $query): mixed
    {
        return $this->store()->get($this->key($query));
    }

    public function put(RankingQueryData $query, mixed $value, ?int $ttlSeconds = null): void
    {
        $this->store()->put($this->key($query), $value, $ttlSeconds ?? self::TTL_SECONDS);
    }

    public function forget(RankingQueryData $query): void
    {
        $this->store()->forget($this->key($query));
    }

    public function flushType(string $type, string $period): void
    {
        $versionKey = $this->versionKey($type, $period);
        $current = (int) $this->store()->get($versionKey, 1);
        $this->store()->forever($versionKey, $current + 1);
    }

    public function remember(RankingQueryData $query, callable $callback, ?int $ttlSeconds = null): mixed
    {
        return $this->store()->remember(
            $this->key($query),
            $ttlSeconds ?? self::TTL_SECONDS,
            $callback,
        );
    }

    public function version(string $type, string $period): int
    {
        return (int) $this->store()->get($this->versionKey($type, $period), 1);
    }

    private function versionKey(string $type, string $period): string
    {
        return self::KEY_PREFIX.':version:'.$type.':'.$period;
    }

    private function store(): CacheRepository
    {
        return Cache::store();
    }
}

<?php

namespace App\Features\Security\Support;

use App\Features\Security\DTO\SecurityEventData;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Redis-backed scratchpad handed to every scanner.
 *
 * Scanners must not run their own queries against gameplay tables. They use
 * these O(1) sliding-window primitives instead, which is what lets detection
 * scale to millions of events without touching the source modules' databases.
 */
final class SecurityContext
{
    /** @var array<string, mixed> */
    private array $memo = [];

    public function __construct(
        public readonly SecurityEventData $event,
        private readonly ?CacheRepository $cache = null,
    ) {}

    /**
     * Increments a counter bucket and returns the new value.
     */
    public function bump(string $bucket, int $ttlSeconds = 3600, int $by = 1): int
    {
        $key = $this->key($bucket);
        $store = $this->store();

        $current = (int) $store->get($key, 0);
        $next = $current + $by;
        $store->put($key, $next, $ttlSeconds);

        return $next;
    }

    public function counter(string $bucket): int
    {
        return (int) $this->store()->get($this->key($bucket), 0);
    }

    /**
     * Appends a sample to a bounded ring buffer and returns the buffer.
     *
     * @return list<float>
     */
    public function pushSample(string $bucket, float $value, int $limit = 60, int $ttlSeconds = 3600): array
    {
        $key = $this->key($bucket);
        $store = $this->store();

        $samples = $store->get($key);
        $samples = is_array($samples) ? array_values(array_filter($samples, 'is_numeric')) : [];
        $samples[] = $value;
        if (count($samples) > $limit) {
            $samples = array_slice($samples, -$limit);
        }
        $store->put($key, $samples, $ttlSeconds);

        /** @var list<float> $out */
        $out = array_map('floatval', $samples);

        return $out;
    }

    /**
     * @return list<float>
     */
    public function samples(string $bucket): array
    {
        $samples = $this->store()->get($this->key($bucket));

        /** @var list<float> $out */
        $out = is_array($samples) ? array_values(array_map('floatval', array_filter($samples, 'is_numeric'))) : [];

        return $out;
    }

    /**
     * Adds a member to a bounded set and returns its distinct size. Used for
     * "how many users share this device" style checks.
     */
    public function trackDistinct(string $bucket, ?string $member, int $limit = 50, int $ttlSeconds = 86400): int
    {
        if ($member === null || $member === '') {
            return 0;
        }

        $key = $this->key($bucket);
        $store = $this->store();

        $members = $store->get($key);
        $members = is_array($members) ? $members : [];
        $members[$member] = true;
        if (count($members) > $limit) {
            $members = array_slice($members, -$limit, null, true);
        }
        $store->put($key, $members, $ttlSeconds);

        return count($members);
    }

    /**
     * Returns true the first time a token is seen inside the window; false on
     * every repeat. The replay/duplicate-settlement detectors rely on this.
     */
    public function isFirstOccurrence(string $bucket, int $ttlSeconds = 900): bool
    {
        $store = $this->store();
        $key = $this->key($bucket);

        if ($store->get($key) !== null) {
            return false;
        }

        $store->put($key, 1, $ttlSeconds);

        return true;
    }

    public function remember(string $bucket, int $ttlSeconds, Closure $callback): mixed
    {
        if (array_key_exists($bucket, $this->memo)) {
            return $this->memo[$bucket];
        }

        return $this->memo[$bucket] = $this->store()->remember($this->key($bucket), $ttlSeconds, $callback);
    }

    /**
     * Scopes a bucket to the acting user, falling back to the device or IP so
     * unauthenticated abuse is still correlated.
     */
    public function actorKey(): string
    {
        return $this->event->userId
            ?? $this->event->deviceHash
            ?? $this->event->ipHash
            ?? 'anonymous';
    }

    private function key(string $bucket): string
    {
        return 'sec:'.$bucket;
    }

    private function store(): CacheRepository
    {
        return $this->cache ?? Cache::store((string) config('security_platform.cache_store', config('cache.default')));
    }
}

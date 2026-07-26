<?php

namespace App\Features\GameAnalytics\Services;

use App\Features\GameAnalytics\DTO\NormalizedAnalyticsEvent;
use App\Features\GameAnalytics\Jobs\PersistAnalyticsEventJob;
use App\Features\GameAnalytics\Repositories\Contracts\AnalyticsRepository;
use App\Features\GameAnalytics\Support\EventNormalizer;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Lightweight ingestion entrypoint. Heavy work is always queued.
 */
final class AnalyticsCollector
{
    public function __construct(
        private readonly EventNormalizer $normalizer,
        private readonly AnalyticsRepository $repository,
    ) {}

    /**
     * @param  array<int, mixed>  $payload
     */
    public function collectFromDomainEvent(string $eventName, array $payload = []): void
    {
        $normalized = $this->normalizer->normalize($eventName, $payload);
        if ($normalized === null) {
            return;
        }

        $this->queueNormalized($normalized);
    }

    public function queueNormalized(NormalizedAnalyticsEvent $event): void
    {
        // Analytics ingestion is fire-and-forget. A broker/queue outage (for
        // example during deploy build steps where Redis is unavailable) must
        // never break the originating request or console command.
        try {
            PersistAnalyticsEventJob::dispatch($event)
                ->onConnection((string) config('game_analytics.queue_connection', 'database'))
                ->onQueue((string) config('game_analytics.queue', 'analytics'));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function persist(NormalizedAnalyticsEvent $event): void
    {
        $stored = $this->repository->appendEvent($event);
        if ($stored === null) {
            return;
        }

        $this->bumpRealtimeCounters($event);
    }

    private function bumpRealtimeCounters(NormalizedAnalyticsEvent $event): void
    {
        $cache = $this->cache();
        $date = $event->occurredAt->toDateString();
        $cache->increment("ga:ccu:events:{$date}");
        if ($event->userId !== null) {
            $cache->forever("ga:dau:{$date}:{$event->userId}", 1);
        }
        if ((float) $event->revenue > 0) {
            $cache->increment("ga:revenue:{$date}", (int) round(((float) $event->revenue) * 10000));
        }
        if ($event->gameSlug !== null) {
            $cache->increment("ga:game:{$event->gameSlug}:{$date}");
        }
    }

    private function cache(): CacheRepository
    {
        return Cache::store((string) config('game_analytics.cache_store', config('cache.default')));
    }
}

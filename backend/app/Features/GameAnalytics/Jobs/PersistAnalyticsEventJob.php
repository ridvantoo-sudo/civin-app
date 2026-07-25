<?php

namespace App\Features\GameAnalytics\Jobs;

use App\Features\GameAnalytics\DTO\NormalizedAnalyticsEvent;
use App\Features\GameAnalytics\Services\AnalyticsCollector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class PersistAnalyticsEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly NormalizedAnalyticsEvent $event)
    {
        $this->onConnection((string) config('game_analytics.queue_connection', 'redis'));
        $this->onQueue((string) config('game_analytics.queue', 'analytics'));
    }

    public function handle(AnalyticsCollector $collector): void
    {
        $collector->persist($this->event);
    }
}

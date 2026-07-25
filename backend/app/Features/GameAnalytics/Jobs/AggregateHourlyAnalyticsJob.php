<?php

namespace App\Features\GameAnalytics\Jobs;

use App\Features\GameAnalytics\Services\AnalyticsAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class AggregateHourlyAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ?string $hour = null)
    {
        $this->onConnection((string) config('game_analytics.queue_connection', 'redis'));
        $this->onQueue((string) config('game_analytics.queue', 'analytics'));
    }

    public function handle(AnalyticsAggregator $aggregator): void
    {
        $hour = $this->hour ? CarbonImmutable::parse($this->hour) : null;
        $aggregator->aggregateHour($hour);
    }
}

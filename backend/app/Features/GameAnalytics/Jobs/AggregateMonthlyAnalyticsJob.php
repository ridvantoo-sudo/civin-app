<?php

namespace App\Features\GameAnalytics\Jobs;

use App\Features\GameAnalytics\Services\AnalyticsAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class AggregateMonthlyAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ?string $month = null)
    {
        $this->onConnection((string) config('game_analytics.queue_connection', 'redis'));
        $this->onQueue((string) config('game_analytics.queue', 'analytics'));
    }

    public function handle(AnalyticsAggregator $aggregator): void
    {
        $month = $this->month ? CarbonImmutable::parse($this->month) : null;
        $aggregator->aggregateMonth($month);
    }
}

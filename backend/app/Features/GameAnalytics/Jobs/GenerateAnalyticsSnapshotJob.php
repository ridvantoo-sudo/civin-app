<?php

namespace App\Features\GameAnalytics\Jobs;

use App\Features\GameAnalytics\Services\SnapshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class GenerateAnalyticsSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $type = 'executive')
    {
        $this->onConnection((string) config('game_analytics.queue_connection', 'redis'));
        $this->onQueue((string) config('game_analytics.queue', 'analytics'));
    }

    public function handle(SnapshotService $snapshots): void
    {
        $snapshots->capture($this->type);
    }
}

<?php

namespace App\Features\GameAnalytics\Jobs;

use App\Features\GameAnalytics\DTO\ExportRequestData;
use App\Features\GameAnalytics\Services\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ExportAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ExportRequestData $request)
    {
        $this->onConnection((string) config('game_analytics.queue_connection', 'redis'));
        $this->onQueue((string) config('game_analytics.queue', 'analytics'));
    }

    /**
     * @return array{path: string, disk: string, filename: string, mime: string}
     */
    public function handle(ExportService $exportService): array
    {
        return $exportService->export($this->request);
    }
}

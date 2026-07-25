<?php

namespace App\Features\Security\Jobs;

use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Services\SecurityCollector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Every security analysis runs here — never on a gameplay request.
 */
final class AnalyzeSecurityEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /** Retry with backoff so a transient Redis blip does not drop detections. */
    public function backoff(): array
    {
        return [5, 15, 45];
    }

    public function __construct(public readonly SecurityEventData $event)
    {
        $this->onConnection((string) config('security_platform.queue_connection', 'redis'));
        $this->onQueue((string) config('security_platform.queue', 'security'));
    }

    public function handle(SecurityCollector $collector): void
    {
        $collector->process($this->event);
    }
}

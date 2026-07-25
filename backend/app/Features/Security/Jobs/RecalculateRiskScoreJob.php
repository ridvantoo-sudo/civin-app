<?php

namespace App\Features\Security\Jobs;

use App\Features\Security\Engine\RiskEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Applies time-based decay so scores recover once abuse stops.
 */
final class RecalculateRiskScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly string $userId)
    {
        $this->onConnection((string) config('security_platform.queue_connection', 'redis'));
        $this->onQueue((string) config('security_platform.queue', 'security'));
    }

    public function handle(RiskEngine $engine): void
    {
        $score = $engine->profileFor($this->userId);
        $engine->decay($score);
        $score->save();
    }
}

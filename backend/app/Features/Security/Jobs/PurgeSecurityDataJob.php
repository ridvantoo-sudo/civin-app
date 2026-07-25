<?php

namespace App\Features\Security\Jobs;

use App\Features\Security\Repositories\Contracts\SecurityRepository;
use App\Features\Security\Services\SecurityAuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Enforces the configured data-retention windows.
 *
 * Security telemetry is kept only as long as it is useful for fraud
 * prevention; everything older is deleted.
 */
final class PurgeSecurityDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct()
    {
        $this->onConnection((string) config('security_platform.queue_connection', 'redis'));
        $this->onQueue((string) config('security_platform.queue', 'security'));
    }

    public function handle(SecurityRepository $repository, SecurityAuditLogger $logger): void
    {
        $retention = (array) config('security_platform.retention', []);

        $plan = [
            ['security_events', 'occurred_at', (int) ($retention['events_days'] ?? 90)],
            ['security_logs', 'created_at', (int) ($retention['logs_days'] ?? 365)],
            ['security_alerts', 'created_at', (int) ($retention['alerts_days'] ?? 180)],
            ['security_sessions', 'last_seen_at', (int) ($retention['sessions_days'] ?? 60)],
            ['security_networks', 'last_seen_at', (int) ($retention['networks_days'] ?? 120)],
        ];

        $deleted = [];
        foreach ($plan as [$table, $column, $days]) {
            $deleted[$table] = $repository->purgeOlderThan($table, $column, $days);
        }

        $logger->retention('retention.purged', 'Applied security data retention policy', $deleted);
    }
}

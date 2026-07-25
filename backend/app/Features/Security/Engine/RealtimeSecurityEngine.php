<?php

namespace App\Features\Security\Engine;

use App\Features\Security\Events\AccountFlagged;
use App\Features\Security\Events\AccountSuspended;
use App\Features\Security\Events\FraudDetected;
use App\Features\Security\Events\GameBlocked;
use App\Features\Security\Events\SecurityAlertRaised;
use App\Features\Security\Events\WalletLocked;
use App\Features\Security\Models\SecurityAlert;
use App\Features\Security\Models\SecurityBan;
use App\Features\Security\Models\SecurityFlag;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Single exit point for every security broadcast.
 *
 * Alerts are throttled per user+signal so a burst of identical detections
 * cannot flood the monitor channel — important at 100k concurrent users.
 */
final class RealtimeSecurityEngine
{
    public function __construct(private readonly ?CacheRepository $cache = null) {}

    public function alertRaised(SecurityAlert $alert): void
    {
        $key = 'sec:bcast:alert:'.($alert->user_id ?? 'anon').':'.$alert->signal;
        if (! $this->shouldBroadcast($key)) {
            return;
        }

        $this->dispatch(static fn () => SecurityAlertRaised::dispatch($alert));
    }

    /**
     * @param  list<string>  $signals
     * @param  array<string, mixed>  $evidence
     */
    public function fraudDetected(?string $userId, string $surface, array $signals, float $confidence, array $evidence = []): void
    {
        $key = 'sec:bcast:fraud:'.($userId ?? 'anon').':'.$surface;
        if (! $this->shouldBroadcast($key)) {
            return;
        }

        $this->dispatch(static fn () => FraudDetected::dispatch($userId, $surface, $signals, $confidence, $evidence));
    }

    public function accountFlagged(SecurityFlag $flag): void
    {
        $this->dispatch(static fn () => AccountFlagged::dispatch($flag));
    }

    public function accountSuspended(SecurityBan $ban): void
    {
        $this->dispatch(static fn () => AccountSuspended::dispatch($ban));

        if (in_array($ban->scope, [SecurityBan::SCOPE_WALLET, SecurityBan::SCOPE_PLATFORM], true)) {
            $this->dispatch(static fn () => WalletLocked::dispatch(
                (string) $ban->user_id,
                (string) $ban->reason,
                (string) $ban->id,
                $ban->expires_at?->toISOString(),
            ));
        }

        if (in_array($ban->scope, [SecurityBan::SCOPE_GAMES, SecurityBan::SCOPE_PLATFORM], true)) {
            $this->dispatch(static fn () => GameBlocked::dispatch(
                (string) $ban->user_id,
                (string) $ban->reason,
                null,
                (string) $ban->id,
            ));
        }
    }

    private function shouldBroadcast(string $key): bool
    {
        $throttle = (int) config('security_platform.realtime.broadcast_throttle_seconds', 5);
        if ($throttle <= 0) {
            return true;
        }

        try {
            $store = $this->cache ?? Cache::store((string) config('security_platform.cache_store', config('cache.default')));
            if ($store->get($key) !== null) {
                return false;
            }
            $store->put($key, 1, $throttle);
        } catch (Throwable) {
            return true;
        }

        return true;
    }

    private function dispatch(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable) {
            // A broadcasting outage must never fail the analysis job.
        }
    }
}

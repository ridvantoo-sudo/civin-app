<?php

namespace App\Features\Security\Repositories\Eloquent;

use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Models\SecurityAlert;
use App\Features\Security\Models\SecurityBan;
use App\Features\Security\Models\SecurityDevice;
use App\Features\Security\Models\SecurityEvent;
use App\Features\Security\Models\SecurityReview;
use App\Features\Security\Models\SecurityScore;
use App\Features\Security\Repositories\Contracts\SecurityRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentSecurityRepository implements SecurityRepository
{
    public function appendEvent(SecurityEventData $event): ?SecurityEvent
    {
        try {
            return SecurityEvent::query()->create($event->toPersistenceArray());
        } catch (QueryException $exception) {
            // Unique violation on idempotency_key: the event is already stored.
            if ($this->isUniqueViolation($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    public function markAnalyzed(string $eventId, float $riskContribution): void
    {
        SecurityEvent::query()
            ->whereKey($eventId)
            ->update([
                'analyzed' => true,
                'risk_contribution' => $riskContribution,
            ]);
    }

    public function historyForUser(string $userId, int $limit = 50): Collection
    {
        return SecurityEvent::query()
            ->where('user_id', $userId)
            ->orderByDesc('occurred_at')
            ->limit(max(1, min($limit, 200)))
            ->get();
    }

    public function alertsForUser(string $userId, int $limit = 50): Collection
    {
        return SecurityAlert::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(max(1, min($limit, 200)))
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $since = CarbonImmutable::now()->subDay();

        return [
            'events_24h' => SecurityEvent::query()->where('occurred_at', '>=', $since)->count(),
            'alerts_open' => SecurityAlert::query()->open()->count(),
            'alerts_critical_24h' => SecurityAlert::query()
                ->where('severity', 'critical')
                ->where('created_at', '>=', $since)
                ->count(),
            'reviews_pending' => SecurityReview::query()->pending()->count(),
            'bans_active' => SecurityBan::query()->effective()->count(),
            'devices_compromised' => SecurityDevice::query()
                ->where(function ($query): void {
                    $query->where('is_rooted', true)
                        ->orWhere('is_jailbroken', true)
                        ->orWhere('is_tampered', true);
                })
                ->count(),
            'high_risk_accounts' => SecurityScore::query()
                ->whereIn('band', [SecurityScore::BAND_HIGH, SecurityScore::BAND_CRITICAL])
                ->count(),
        ];
    }

    public function purgeOlderThan(string $table, string $column, int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        return DB::table($table)
            ->where($column, '<', CarbonImmutable::now()->subDays($days))
            ->delete();
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();
        if (in_array($code, ['23000', '23505'], true)) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique') || str_contains($message, 'duplicate');
    }
}

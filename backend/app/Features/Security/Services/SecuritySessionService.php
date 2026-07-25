<?php

namespace App\Features\Security\Services;

use App\Features\Security\Models\SecuritySession;
use App\Features\Security\Support\Fingerprint;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class SecuritySessionService
{
    /**
     * Registers or refreshes a session and reports how many are concurrent.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function verify(
        string $userId,
        string $sessionId,
        ?string $deviceId = null,
        ?string $ipAddress = null,
        ?string $countryCode = null,
        ?string $platform = null,
        array $metadata = [],
    ): SecuritySession {
        $sessionHash = Fingerprint::session($sessionId) ?? Fingerprint::session('unknown');

        return DB::transaction(function () use (
            $userId,
            $sessionHash,
            $deviceId,
            $ipAddress,
            $countryCode,
            $platform,
            $metadata,
        ): SecuritySession {
            /** @var SecuritySession $session */
            $session = SecuritySession::query()->firstOrCreate(
                ['user_id' => $userId, 'session_hash' => $sessionHash],
                [
                    'device_hash' => Fingerprint::device($deviceId),
                    'ip_hash' => Fingerprint::ip($ipAddress),
                    'country_code' => $countryCode !== null ? strtoupper(substr($countryCode, 0, 8)) : null,
                    'platform' => $platform !== null ? strtolower(substr($platform, 0, 32)) : null,
                    'status' => SecuritySession::STATUS_ACTIVE,
                    'request_count' => 0,
                    'started_at' => CarbonImmutable::now(),
                ],
            );

            $session->request_count = $session->request_count + 1;
            $session->last_seen_at = CarbonImmutable::now();
            $session->status = SecuritySession::STATUS_ACTIVE;
            $session->metadata = $metadata === [] ? $session->metadata : $metadata;

            $concurrent = $this->concurrentCount($userId, $sessionHash);
            $limit = (int) config('security_platform.thresholds.sessions.max_concurrent', 3);

            $session->is_verified = $concurrent <= $limit;
            $session->risk_score = $concurrent > $limit
                ? round(min(100.0, 40.0 + (($concurrent - $limit) * 15.0)), 2)
                : 0.0;

            if ($concurrent > $limit) {
                $session->anomaly_count = $session->anomaly_count + 1;
            }

            $session->save();

            return $session;
        }, 3);
    }

    public function concurrentCount(string $userId, ?string $excludeHash = null): int
    {
        return (int) SecuritySession::query()
            ->where('user_id', $userId)
            ->where('status', SecuritySession::STATUS_ACTIVE)
            ->where('last_seen_at', '>=', CarbonImmutable::now()->subMinutes(15))
            ->when($excludeHash !== null, fn ($query) => $query->where('session_hash', '!=', $excludeHash))
            ->count() + ($excludeHash !== null ? 1 : 0);
    }

    /**
     * @return Collection<int, SecuritySession>
     */
    public function forUser(string $userId): Collection
    {
        return SecuritySession::query()
            ->where('user_id', $userId)
            ->orderByDesc('last_seen_at')
            ->limit(50)
            ->get();
    }

    public function revoke(SecuritySession $session): SecuritySession
    {
        $session->status = SecuritySession::STATUS_REVOKED;
        $session->ended_at = CarbonImmutable::now();
        $session->save();

        return $session;
    }
}

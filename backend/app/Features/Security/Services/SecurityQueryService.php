<?php

namespace App\Features\Security\Services;

use App\Features\Security\DTO\RuleDefinition;
use App\Features\Security\Engine\RiskEngine;
use App\Features\Security\Models\SecurityAlert;
use App\Features\Security\Models\SecurityBan;
use App\Features\Security\Models\SecurityEvent;
use App\Features\Security\Models\SecurityFlag;
use App\Features\Security\Models\SecurityScore;
use App\Features\Security\Repositories\Contracts\SecurityRepository;
use App\Features\Security\Rules\RuleProvider;

/**
 * Read model for the security API.
 *
 * Everything returned here is scoped to the requesting user unless the caller
 * is an administrator, and never exposes another account's identifiers.
 */
final class SecurityQueryService
{
    public function __construct(
        private readonly SecurityRepository $repository,
        private readonly RiskEngine $riskEngine,
        private readonly RuleProvider $rules,
        private readonly SecurityDeviceService $devices,
        private readonly SecuritySessionService $sessions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function selfSummary(string $userId): array
    {
        $score = $this->riskEngine->profileFor($userId);

        $restrictions = SecurityBan::query()
            ->effective()
            ->where('user_id', $userId)
            ->get()
            ->map(static fn (SecurityBan $ban): array => [
                'scope' => $ban->scope,
                'action' => $ban->action,
                'reason' => $ban->reason,
                'expires_at' => $ban->expires_at?->toISOString(),
            ])
            ->all();

        return [
            'user_id' => $userId,
            'scores' => $this->scorePayload($score),
            'restrictions' => $restrictions,
            'wallet_locked' => $this->hasScope($restrictions, [SecurityBan::SCOPE_WALLET, SecurityBan::SCOPE_PLATFORM]),
            'games_blocked' => $this->hasScope($restrictions, [SecurityBan::SCOPE_GAMES, SecurityBan::SCOPE_PLATFORM]),
            'live_blocked' => $this->hasScope($restrictions, [SecurityBan::SCOPE_LIVE, SecurityBan::SCOPE_PLATFORM]),
            'active_flag_count' => SecurityFlag::query()->active()->where('user_id', $userId)->count(),
            'open_alert_count' => SecurityAlert::query()->open()->where('user_id', $userId)->count(),
            'device_count' => $this->devices->forUser($userId)->count(),
            'active_session_count' => $this->sessions->concurrentCount($userId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function riskProfile(string $userId): array
    {
        $score = $this->riskEngine->profileFor($userId);

        $flags = SecurityFlag::query()
            ->active()
            ->where('user_id', $userId)
            ->orderByDesc('hit_count')
            ->limit(20)
            ->get()
            ->map(static fn (SecurityFlag $flag): array => [
                'flag' => $flag->flag,
                'severity' => $flag->severity,
                'surface' => $flag->surface,
                'hit_count' => $flag->hit_count,
                'confidence' => (float) $flag->confidence,
                'last_flagged_at' => $flag->last_flagged_at?->toISOString(),
            ])
            ->all();

        return [
            'user_id' => $userId,
            'scores' => $this->scorePayload($score),
            'band' => $score->band,
            'breakdown' => $score->breakdown ?? [],
            'flags' => $flags,
            'recommended_action' => $this->riskEngine->escalationFor($score),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function devices(string $userId): array
    {
        return [
            'devices' => $this->devices->forUser($userId)->map(static fn ($device): array => [
                'id' => $device->id,
                'platform' => $device->platform,
                'model' => $device->model,
                'os_version' => $device->os_version,
                'app_version' => $device->app_version,
                'is_emulator' => $device->is_emulator,
                'is_rooted' => $device->is_rooted,
                'is_jailbroken' => $device->is_jailbroken,
                'is_tampered' => $device->is_tampered,
                'is_cloned' => $device->is_cloned,
                'is_trusted' => $device->is_trusted,
                'is_blocked' => $device->is_blocked,
                'risk_score' => (float) $device->risk_score,
                'seen_count' => $device->seen_count,
                'last_seen_at' => $device->last_seen_at?->toISOString(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function history(string $userId, int $limit = 50): array
    {
        $events = $this->repository->historyForUser($userId, $limit)
            ->map(static fn (SecurityEvent $event): array => [
                'id' => $event->id,
                'event' => $event->event,
                'surface' => $event->surface,
                'category' => $event->category,
                'risk_contribution' => (float) $event->risk_contribution,
                'occurred_at' => $event->occurred_at?->toISOString(),
            ])
            ->all();

        $alerts = $this->repository->alertsForUser($userId, $limit)
            ->map(static fn (SecurityAlert $alert): array => [
                'id' => $alert->id,
                'signal' => $alert->signal,
                'title' => $alert->title,
                'severity' => $alert->severity,
                'surface' => $alert->surface,
                'action' => $alert->action,
                'status' => $alert->status,
                'confidence' => (float) $alert->confidence,
                'created_at' => $alert->created_at?->toISOString(),
            ])
            ->all();

        return ['events' => $events, 'alerts' => $alerts];
    }

    /**
     * Rule metadata is safe to expose: it tells honest clients what is
     * enforced without revealing detector internals.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rules' => array_map(
                static fn (RuleDefinition $rule): array => [
                    'key' => $rule->key,
                    'name' => $rule->name,
                    'signal' => $rule->signal,
                    'severity' => $rule->severity,
                    'action' => $rule->action,
                    'priority' => $rule->priority,
                    'is_enabled' => $rule->isEnabled,
                ],
                $this->rules->all(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return $this->repository->overview();
    }

    /**
     * @return array<string, float|string|null>
     */
    private function scorePayload(SecurityScore $score): array
    {
        return [
            'security' => (float) $score->security_score,
            'trust' => (float) $score->trust_score,
            'risk' => (float) $score->risk_score,
            'fraud' => (float) $score->fraud_score,
            'bot' => (float) $score->bot_score,
            'band' => (string) $score->band,
            'last_signal_at' => $score->last_signal_at?->toISOString(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $restrictions
     * @param  list<string>  $scopes
     */
    private function hasScope(array $restrictions, array $scopes): bool
    {
        foreach ($restrictions as $restriction) {
            if (in_array($restriction['scope'] ?? '', $scopes, true)) {
                return true;
            }
        }

        return false;
    }
}

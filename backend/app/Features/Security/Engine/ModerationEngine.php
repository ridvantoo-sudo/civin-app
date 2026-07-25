<?php

namespace App\Features\Security\Engine;

use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\DTO\SecurityVerdict;
use App\Features\Security\Models\SecurityAlert;
use App\Features\Security\Models\SecurityBan;
use App\Features\Security\Models\SecurityFlag;
use App\Features\Security\Models\SecurityReview;
use App\Features\Security\Services\SecurityAuditLogger;
use App\Features\Security\Support\SecurityAction;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySurface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Applies a verdict.
 *
 * Crucially, "applying" means recording security state and broadcasting — it
 * never calls into Wallet, Games, Live or any other module. Those modules are
 * free to consult the security API, but nothing here mutates their data.
 */
final class ModerationEngine
{
    public function __construct(
        private readonly RealtimeSecurityEngine $realtime,
        private readonly SecurityAuditLogger $logger,
    ) {}

    /**
     * @param  list<DetectedSignal>  $signals
     */
    public function apply(
        SecurityVerdict $verdict,
        SecurityEventData $event,
        array $signals,
        ?string $eventId = null,
    ): void {
        if ($signals === []) {
            return;
        }

        $userId = $event->userId;
        $alerts = $this->recordAlerts($verdict, $event, $signals, $eventId);

        if ($userId !== null) {
            $this->recordFlags($signals, $userId, $event);
        }

        $enforcementEnabled = (bool) config('security_platform.enforcement_enabled', true);
        $action = $verdict->action;

        if (! $enforcementEnabled && SecurityAction::isEnforcement($action)) {
            $this->logger->decision(
                'enforcement.shadowed',
                "Enforcement disabled; {$action} downgraded to warn",
                ['signals' => $verdict->signalNames()],
                $userId,
            );
            $action = SecurityAction::WARN;
        }

        match ($action) {
            SecurityAction::REVIEW => $this->queueReview($verdict, $event, $alerts[0] ?? null),
            SecurityAction::SUSPEND, SecurityAction::BAN => $this->issueBan($verdict, $event, $action),
            SecurityAction::THROTTLE => $this->recordThrottle($verdict, $event),
            default => null,
        };

        $this->announceFraud($verdict, $event, $signals);
    }

    /**
     * @param  list<DetectedSignal>  $signals
     * @return list<SecurityAlert>
     */
    private function recordAlerts(
        SecurityVerdict $verdict,
        SecurityEventData $event,
        array $signals,
        ?string $eventId,
    ): array {
        $matchesBySignal = [];
        foreach ($verdict->matches as $match) {
            $matchesBySignal[$match->signal] ??= $match;
        }

        $alerts = [];
        foreach ($signals as $signal) {
            $match = $matchesBySignal[$signal->signal] ?? null;

            // Low-severity observations that matched no rule stay as flags only.
            if ($match === null && ! $signal->isAtLeast(SecuritySeverity::HIGH)) {
                continue;
            }

            $alert = SecurityAlert::query()->create([
                'user_id' => $event->userId,
                'event_id' => $eventId,
                'rule_key' => $match?->ruleKey,
                'signal' => $signal->signal,
                'title' => $signal->title,
                'severity' => $match?->severity ?? $signal->severity,
                'surface' => $signal->surface ?? $event->surface,
                'action' => $match?->action ?? SecurityAction::WARN,
                'status' => SecurityAlert::STATUS_OPEN,
                'confidence' => $signal->confidence,
                'risk_delta' => $signal->weightedScore(),
                'device_hash' => $event->deviceHash,
                'ip_hash' => $event->ipHash,
                'evidence' => $signal->evidence,
                'metadata' => [
                    'event' => $event->eventName,
                    'occurred_at' => $event->occurredAt->toIso8601String(),
                ],
            ]);

            $alerts[] = $alert;
            $this->realtime->alertRaised($alert);
        }

        return $alerts;
    }

    /**
     * @param  list<DetectedSignal>  $signals
     */
    private function recordFlags(array $signals, string $userId, SecurityEventData $event): void
    {
        foreach ($signals as $signal) {
            $flag = DB::transaction(function () use ($signal, $userId, $event): SecurityFlag {
                /** @var SecurityFlag $flag */
                $flag = SecurityFlag::query()->firstOrCreate(
                    ['user_id' => $userId, 'flag' => $signal->signal],
                    [
                        'severity' => $signal->severity,
                        'surface' => $signal->surface ?? $event->surface,
                        'status' => SecurityFlag::STATUS_ACTIVE,
                        'confidence' => $signal->confidence,
                        'hit_count' => 0,
                        'evidence' => $signal->evidence,
                        'first_flagged_at' => CarbonImmutable::now(),
                    ],
                );

                $flag->hit_count = $flag->hit_count + 1;
                $flag->status = SecurityFlag::STATUS_ACTIVE;
                $flag->last_flagged_at = CarbonImmutable::now();
                $flag->confidence = max((float) $flag->confidence, $signal->confidence);
                if (SecuritySeverity::weight($signal->severity) > SecuritySeverity::weight((string) $flag->severity)) {
                    $flag->severity = $signal->severity;
                }
                $flag->evidence = $signal->evidence;
                $flag->save();

                return $flag;
            }, 3);

            $this->realtime->accountFlagged($flag);
        }
    }

    private function queueReview(SecurityVerdict $verdict, SecurityEventData $event, ?SecurityAlert $alert): void
    {
        SecurityReview::query()->create([
            'user_id' => $event->userId,
            'alert_id' => $alert?->id,
            'queue' => $event->surface,
            'reason' => mb_substr($verdict->reason ?? 'Automated review requested', 0, 500),
            'status' => SecurityReview::STATUS_PENDING,
            'priority' => $verdict->highestSeverity() === SecuritySeverity::CRITICAL ? 'urgent' : 'normal',
            'risk_score' => $verdict->riskDelta,
            'context' => [
                'signals' => $verdict->signalNames(),
                'event' => $event->eventName,
            ],
            'due_at' => CarbonImmutable::now()->addHours(24),
        ]);

        $this->logger->decision(
            'review.queued',
            'Case queued for manual review',
            ['signals' => $verdict->signalNames()],
            $event->userId,
        );
    }

    private function issueBan(SecurityVerdict $verdict, SecurityEventData $event, string $action): void
    {
        if ($event->userId === null) {
            return;
        }

        $scope = $this->scopeFor($event->surface, $action);

        $existing = SecurityBan::query()
            ->effective()
            ->where('user_id', $event->userId)
            ->where('scope', $scope)
            ->first();

        if ($existing !== null) {
            return;
        }

        $ban = SecurityBan::query()->create([
            'user_id' => $event->userId,
            'scope' => $scope,
            'action' => $action,
            'reason' => mb_substr($verdict->reason ?? 'Automated enforcement', 0, 500),
            'rule_key' => $verdict->matches[0]->ruleKey ?? null,
            'status' => SecurityBan::STATUS_ACTIVE,
            'is_automatic' => true,
            'device_hash' => $event->deviceHash,
            'ip_hash' => $event->ipHash,
            'evidence' => ['signals' => $verdict->signalNames()],
            'starts_at' => CarbonImmutable::now(),
            // Automated suspensions always expire; only a human makes it permanent.
            'expires_at' => $action === SecurityAction::BAN ? null : CarbonImmutable::now()->addHours(24),
        ]);

        $this->realtime->accountSuspended($ban);

        $this->logger->decision(
            'enforcement.'.$action,
            "Automatic {$action} applied to scope {$scope}",
            ['signals' => $verdict->signalNames(), 'ban_id' => $ban->id],
            $event->userId,
        );
    }

    private function recordThrottle(SecurityVerdict $verdict, SecurityEventData $event): void
    {
        $this->logger->decision(
            'enforcement.throttle',
            'Throttle recommended for actor',
            [
                'signals' => $verdict->signalNames(),
                'surface' => $event->surface,
            ],
            $event->userId,
        );
    }

    /**
     * @param  list<DetectedSignal>  $signals
     */
    private function announceFraud(SecurityVerdict $verdict, SecurityEventData $event, array $signals): void
    {
        $critical = array_values(array_filter(
            $signals,
            static fn (DetectedSignal $signal): bool => $signal->isAtLeast(SecuritySeverity::CRITICAL),
        ));

        if ($critical === []) {
            return;
        }

        $confidence = 0.0;
        foreach ($critical as $signal) {
            $confidence = max($confidence, $signal->confidence);
        }

        $this->realtime->fraudDetected(
            $event->userId,
            $event->surface,
            array_values(array_unique(array_map(
                static fn (DetectedSignal $signal): string => $signal->signal,
                $critical,
            ))),
            $confidence,
            ['action' => $verdict->action, 'event' => $event->eventName],
        );
    }

    private function scopeFor(string $surface, string $action): string
    {
        if ($action === SecurityAction::BAN) {
            return SecurityBan::SCOPE_PLATFORM;
        }

        return match ($surface) {
            SecuritySurface::WALLET => SecurityBan::SCOPE_WALLET,
            SecuritySurface::GAMES => SecurityBan::SCOPE_GAMES,
            SecuritySurface::LIVE, SecuritySurface::VOICE => SecurityBan::SCOPE_LIVE,
            default => SecurityBan::SCOPE_PLATFORM,
        };
    }
}

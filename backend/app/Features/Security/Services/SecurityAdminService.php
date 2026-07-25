<?php

namespace App\Features\Security\Services;

use App\Features\Security\Models\SecurityAlert;
use App\Features\Security\Models\SecurityBan;
use App\Features\Security\Models\SecurityReview;
use App\Features\Security\Models\SecurityRule;
use App\Features\Security\Rules\RuleProvider;
use App\Features\Security\Support\SecurityAction;
use Carbon\CarbonImmutable;

/**
 * Administrative operations for the security panel.
 *
 * Every method writes an audit entry, because the panel is the one place a
 * human can override the engine and that has to be reconstructable later.
 */
final class SecurityAdminService
{
    public function __construct(
        private readonly SecurityAuditLogger $logger,
        private readonly RuleProvider $rules,
    ) {}

    public function acknowledgeAlert(SecurityAlert $alert, ?string $actorId): SecurityAlert
    {
        $alert->status = SecurityAlert::STATUS_ACKNOWLEDGED;
        $alert->save();

        $this->logger->admin('alert.acknowledged', 'Alert acknowledged', $actorId, $alert->user_id, [
            'alert_id' => $alert->id,
            'signal' => $alert->signal,
        ]);

        return $alert;
    }

    public function resolveAlert(SecurityAlert $alert, ?string $actorId, string $note = ''): SecurityAlert
    {
        $alert->status = SecurityAlert::STATUS_RESOLVED;
        $alert->resolved_by = $actorId;
        $alert->resolved_at = CarbonImmutable::now();
        $alert->save();

        $this->logger->admin('alert.resolved', $note !== '' ? $note : 'Alert resolved', $actorId, $alert->user_id, [
            'alert_id' => $alert->id,
            'signal' => $alert->signal,
        ]);

        return $alert;
    }

    public function dismissAlert(SecurityAlert $alert, ?string $actorId, string $reason = ''): SecurityAlert
    {
        $alert->status = SecurityAlert::STATUS_DISMISSED;
        $alert->resolved_by = $actorId;
        $alert->resolved_at = CarbonImmutable::now();
        $alert->save();

        $this->logger->admin(
            'alert.dismissed',
            $reason !== '' ? $reason : 'Alert dismissed as false positive',
            $actorId,
            $alert->user_id,
            ['alert_id' => $alert->id, 'signal' => $alert->signal],
        );

        return $alert;
    }

    /**
     * Manual restriction. Recorded exactly like an automatic one so the rest of
     * the platform reads a single source of truth.
     */
    public function issueBan(
        string $userId,
        string $scope,
        string $action,
        string $reason,
        ?string $actorId,
        ?CarbonImmutable $expiresAt = null,
    ): SecurityBan {
        $ban = SecurityBan::query()->create([
            'user_id' => $userId,
            'scope' => in_array($scope, SecurityBan::scopes(), true) ? $scope : SecurityBan::SCOPE_PLATFORM,
            'action' => SecurityAction::normalize($action),
            'reason' => mb_substr($reason, 0, 191),
            'status' => SecurityBan::STATUS_ACTIVE,
            'is_automatic' => false,
            'issued_by' => $actorId,
            'starts_at' => CarbonImmutable::now(),
            'expires_at' => $expiresAt,
        ]);

        $this->logger->admin('ban.issued', $reason, $actorId, $userId, [
            'ban_id' => $ban->id,
            'scope' => $ban->scope,
            'action' => $ban->action,
            'expires_at' => $expiresAt?->toIso8601String(),
        ]);

        return $ban;
    }

    public function liftBan(SecurityBan $ban, ?string $actorId, string $reason = ''): SecurityBan
    {
        $ban->status = SecurityBan::STATUS_LIFTED;
        $ban->lifted_by = $actorId;
        $ban->lifted_at = CarbonImmutable::now();
        $ban->save();

        $this->logger->admin('ban.lifted', $reason !== '' ? $reason : 'Restriction lifted', $actorId, $ban->user_id, [
            'ban_id' => $ban->id,
            'scope' => $ban->scope,
        ]);

        return $ban;
    }

    public function claimReview(SecurityReview $review, ?string $actorId): SecurityReview
    {
        $review->status = SecurityReview::STATUS_IN_PROGRESS;
        $review->assigned_to = $actorId;
        $review->save();

        $this->logger->admin('review.claimed', 'Review case claimed', $actorId, $review->user_id, [
            'review_id' => $review->id,
        ]);

        return $review;
    }

    public function completeReview(
        SecurityReview $review,
        string $resolution,
        ?string $actorId,
        string $notes = '',
    ): SecurityReview {
        $review->status = SecurityReview::STATUS_COMPLETED;
        $review->resolution = $resolution;
        $review->reviewer_notes = mb_substr($notes, 0, 2000);
        $review->reviewed_by = $actorId;
        $review->reviewed_at = CarbonImmutable::now();
        $review->save();

        $this->logger->admin('review.completed', 'Review resolved as '.$resolution, $actorId, $review->user_id, [
            'review_id' => $review->id,
            'resolution' => $resolution,
        ]);

        return $review;
    }

    /**
     * Rule writes must invalidate the compiled rule set, which is what makes
     * hot reload work without restarting queue workers.
     */
    public function ruleChanged(SecurityRule $rule, ?string $actorId, string $action): void
    {
        $this->rules->flush();

        $this->logger->admin('rule.'.$action, 'Security rule '.$action.': '.$rule->key, $actorId, null, [
            'rule_id' => $rule->id,
            'key' => $rule->key,
            'is_enabled' => $rule->is_enabled,
            'action' => $rule->action,
            'threshold' => $rule->threshold,
            'priority' => $rule->priority,
        ]);
    }
}

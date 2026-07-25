<?php

namespace App\Features\Security\Scanners;

use App\Features\Security\AntiCheat\Contracts\Scanner;
use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Support\SecurityContext;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Security\Support\SecuritySurface;
use App\Features\Security\Support\Statistics;

/**
 * Coin, gift and reward farming, plus referral abuse.
 *
 * The strongest tell is circularity: value that keeps returning to the same
 * small group of accounts rather than dispersing.
 */
final class FarmingScanner implements Scanner
{
    public function key(): string
    {
        return 'farming';
    }

    public function supports(SecurityEventData $event): bool
    {
        return in_array($event->surface, [
            SecuritySurface::GIFTS,
            SecuritySurface::WALLET,
            SecuritySurface::GAMES,
            SecuritySurface::AGENCY,
        ], true);
    }

    public function scan(SecurityEventData $event, SecurityContext $context): array
    {
        $thresholds = (array) config('security_platform.thresholds.farming', []);
        $loopWindow = (int) ($thresholds['gift_loop_window_seconds'] ?? 3600);
        $loopCount = (int) ($thresholds['gift_loop_count'] ?? 20);

        $signals = [];
        $signals = array_merge($signals, $this->inspectGiftLoop($event, $context, $loopWindow, $loopCount));
        $signals = array_merge($signals, $this->inspectRewardVelocity($event, $context));
        $signals = array_merge($signals, $this->inspectReferralAbuse($event, $context));

        return $signals;
    }

    /**
     * Detects A→B→A gifting circles that manufacture ranking or agency volume.
     *
     * @return list<DetectedSignal>
     */
    private function inspectGiftLoop(
        SecurityEventData $event,
        SecurityContext $context,
        int $windowSeconds,
        int $limit,
    ): array {
        $sender = $event->userId ?? $this->stringValue($event, ['sender_id', 'senderId', 'from_user_id']);
        $receiver = $this->stringValue($event, ['receiver_id', 'receiverId', 'to_user_id', 'host_id']);

        if ($sender === null || $receiver === null || $sender === $receiver) {
            return [];
        }

        $window = (int) floor($event->occurredAt->getTimestamp() / max(1, $windowSeconds));
        $forward = $context->bump("gift:{$sender}:{$receiver}:{$window}", $windowSeconds * 2);
        $reverse = $context->counter("gift:{$receiver}:{$sender}:{$window}");

        // Reciprocal exchange above the threshold in both directions.
        if ($forward < $limit || $reverse < (int) ceil($limit / 2)) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::REWARD_FARMING,
            severity: SecuritySeverity::HIGH,
            confidence: Statistics::confidenceFromRatio((float) min($forward, $reverse), (float) ($limit / 2)),
            title: 'Circular gifting between paired accounts',
            evidence: [
                'outbound_transfers' => $forward,
                'inbound_transfers' => $reverse,
                'window_seconds' => $windowSeconds,
            ],
            surface: SecuritySurface::GIFTS,
            userId: $event->userId,
        )];
    }

    /**
     * @return list<DetectedSignal>
     */
    private function inspectRewardVelocity(SecurityEventData $event, SecurityContext $context): array
    {
        if (! $this->isRewardEvent($event)) {
            return [];
        }

        $actor = $context->actorKey();
        $hour = $event->occurredAt->format('YmdH');
        $rewards = $context->bump("farm:rw:{$actor}:{$hour}", 7200);

        if ($rewards <= 200) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::REWARD_FARMING,
            severity: SecuritySeverity::HIGH,
            confidence: Statistics::confidenceFromRatio((float) $rewards, 200.0),
            title: 'Reward claims far above normal rate',
            evidence: [
                'rewards_this_hour' => $rewards,
                'baseline' => 200,
                'surface' => $event->surface,
            ],
            surface: $event->surface,
            userId: $event->userId,
        )];
    }

    /**
     * @return list<DetectedSignal>
     */
    private function inspectReferralAbuse(SecurityEventData $event, SecurityContext $context): array
    {
        $referrer = $this->stringValue($event, ['referrer_id', 'referred_by', 'inviter_id']);
        if ($referrer === null) {
            return [];
        }

        $day = $event->occurredAt->format('Ymd');
        $referrals = $context->bump("ref:{$referrer}:{$day}", 172800);

        // Referrals that all share one device are self-referrals.
        $devices = $event->deviceHash !== null
            ? $context->trackDistinct("refdev:{$referrer}:{$day}", $event->deviceHash, 40, 172800)
            : 0;

        if ($referrals < 10) {
            return [];
        }

        $sameDeviceCluster = $devices > 0 && $devices <= 2;
        if (! $sameDeviceCluster && $referrals < 30) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::REFERRAL_ABUSE,
            severity: $sameDeviceCluster ? SecuritySeverity::HIGH : SecuritySeverity::MEDIUM,
            confidence: $sameDeviceCluster ? 86.0 : Statistics::confidenceFromRatio((float) $referrals, 30.0),
            title: 'Referral funnel concentrated on few devices',
            evidence: [
                'referrals_today' => $referrals,
                'distinct_devices' => $devices,
            ],
            surface: SecuritySurface::PLATFORM,
            userId: $event->userId,
        )];
    }

    private function isRewardEvent(SecurityEventData $event): bool
    {
        $name = strtolower($event->eventName);

        foreach (['reward', 'claim', 'granted', 'bonus', 'prize', 'envelope'] as $token) {
            if (str_contains($name, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $keys
     */
    private function stringValue(SecurityEventData $event, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $event->get($key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}

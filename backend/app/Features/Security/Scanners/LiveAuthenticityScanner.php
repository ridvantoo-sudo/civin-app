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
 * Authenticity of live and voice engagement: fake viewers, bot likes and
 * comments, bot gifting and the artificial rankings they produce.
 */
final class LiveAuthenticityScanner implements Scanner
{
    public function key(): string
    {
        return 'live_authenticity';
    }

    public function supports(SecurityEventData $event): bool
    {
        return in_array($event->surface, [
            SecuritySurface::LIVE,
            SecuritySurface::VOICE,
            SecuritySurface::CHAT,
            SecuritySurface::RANKING,
        ], true);
    }

    public function scan(SecurityEventData $event, SecurityContext $context): array
    {
        $thresholds = (array) config('security_platform.thresholds.live', []);

        $signals = [];
        $signals = array_merge($signals, $this->inspectViewerChurn($event, $context, $thresholds));
        $signals = array_merge($signals, $this->inspectCommentRepetition($event, $context));
        $signals = array_merge($signals, $this->inspectRankingInflation($event, $context));

        return $signals;
    }

    /**
     * Bot viewers join and leave almost immediately to inflate counters. A
     * room whose watch times are overwhelmingly sub-second is not real.
     *
     * @param  array<string, mixed>  $thresholds
     * @return list<DetectedSignal>
     */
    private function inspectViewerChurn(
        SecurityEventData $event,
        SecurityContext $context,
        array $thresholds,
    ): array {
        $watchSeconds = $event->get('watch_seconds') ?? $event->get('watchSeconds');
        if (! is_numeric($watchSeconds)) {
            return [];
        }

        $roomId = $event->subjectId ?? $this->roomId($event);
        if ($roomId === null) {
            return [];
        }

        $minSamples = (int) ($thresholds['min_viewer_samples'] ?? 30);
        $ratioLimit = (float) ($thresholds['viewer_join_leave_ratio'] ?? 0.85);

        $total = $context->bump("live:v:{$roomId}", 7200);
        $bounced = $context->bump("live:b:{$roomId}", 7200, ((float) $watchSeconds) < 3 ? 1 : 0);

        if ($total < $minSamples) {
            return [];
        }

        $ratio = $bounced / $total;
        if ($ratio < $ratioLimit) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::FAKE_ENGAGEMENT,
            severity: SecuritySeverity::HIGH,
            confidence: Statistics::confidenceFromRatio($ratio, $ratioLimit),
            title: 'Viewer session lengths indicate bot traffic',
            evidence: [
                'bounce_ratio' => round($ratio, 4),
                'threshold' => $ratioLimit,
                'viewers_sampled' => $total,
                'room_id' => $roomId,
            ],
            surface: $event->surface,
            userId: $event->userId,
        )];
    }

    /**
     * @return list<DetectedSignal>
     */
    private function inspectCommentRepetition(SecurityEventData $event, SecurityContext $context): array
    {
        $body = $event->get('message') ?? $event->get('body') ?? $event->get('text');
        if (! is_string($body) || trim($body) === '') {
            return [];
        }

        $roomId = $event->subjectId ?? $this->roomId($event);
        if ($roomId === null) {
            return [];
        }

        $normalized = substr(hash('sha256', mb_strtolower(preg_replace('/\s+/', ' ', trim($body)) ?? $body)), 0, 32);
        $window = $event->occurredAt->format('YmdHi');

        $repeats = $context->bump("live:c:{$roomId}:{$normalized}:{$window}", 600);
        if ($repeats < 15) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::FAKE_ENGAGEMENT,
            severity: SecuritySeverity::MEDIUM,
            confidence: Statistics::confidenceFromRatio((float) $repeats, 15.0),
            title: 'Identical comment repeated across room',
            evidence: [
                'identical_messages_this_minute' => $repeats,
                'room_id' => $roomId,
            ],
            surface: $event->surface,
            userId: $event->userId,
        )];
    }

    /**
     * Ranking volume concentrated in very few senders is bought, not earned.
     *
     * @return list<DetectedSignal>
     */
    private function inspectRankingInflation(SecurityEventData $event, SecurityContext $context): array
    {
        $receiver = $this->stringValue($event, ['receiver_id', 'host_id', 'to_user_id']);
        $sender = $event->userId ?? $this->stringValue($event, ['sender_id', 'from_user_id']);
        $amount = $event->amountAsFloat();

        if ($receiver === null || $sender === null || $amount <= 0) {
            return [];
        }

        $day = $event->occurredAt->format('Ymd');
        $volume = $context->bump("rank:v:{$receiver}:{$day}", 172800, (int) round($amount));
        $distinctSenders = $context->trackDistinct("rank:s:{$receiver}:{$day}", $sender, 200, 172800);

        // Needs meaningful volume before concentration means anything.
        if ($volume < 100000 || $distinctSenders > 3) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::ARTIFICIAL_RANKING,
            severity: SecuritySeverity::HIGH,
            confidence: min(90.0, 65.0 + ((4 - $distinctSenders) * 8.0)),
            title: 'Ranking volume from a handful of senders',
            evidence: [
                'volume_today' => $volume,
                'distinct_senders' => $distinctSenders,
                'receiver_id' => $receiver,
            ],
            surface: SecuritySurface::RANKING,
            userId: $event->userId,
        )];
    }

    private function roomId(SecurityEventData $event): ?string
    {
        return $this->stringValue($event, ['room_id', 'roomId', 'live_room_id', 'voice_room_id']);
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

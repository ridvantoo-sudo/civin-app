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
 * Detects auto clickers, macros, speed hacks, impossible reaction times and
 * suspiciously perfect timing by looking only at the cadence of events.
 */
final class AutomationScanner implements Scanner
{
    public function key(): string
    {
        return 'automation';
    }

    public function supports(SecurityEventData $event): bool
    {
        return in_array($event->surface, [
            SecuritySurface::GAMES,
            SecuritySurface::PK,
            SecuritySurface::GIFTS,
            SecuritySurface::CHAT,
            SecuritySurface::WALLET,
        ], true);
    }

    public function scan(SecurityEventData $event, SecurityContext $context): array
    {
        $thresholds = (array) config('security_platform.thresholds.automation', []);
        $minSamples = (int) ($thresholds['min_samples'] ?? 6);
        $jitterFloorMs = (float) ($thresholds['interval_jitter_ms'] ?? 45);
        $impossibleReactionMs = (float) ($thresholds['impossible_reaction_ms'] ?? 120);
        $burstPerMinute = (int) ($thresholds['burst_actions_per_minute'] ?? 120);

        $actor = $context->actorKey();
        $signals = [];

        $signals = array_merge($signals, $this->inspectCadence(
            $event,
            $context,
            $actor,
            $minSamples,
            $jitterFloorMs,
        ));

        $reaction = $this->reactionTimeMs($event);
        if ($reaction !== null && $reaction > 0 && $reaction < $impossibleReactionMs) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::IMPOSSIBLE_REACTION,
                severity: SecuritySeverity::HIGH,
                confidence: Statistics::confidenceBelowFloor($reaction, $impossibleReactionMs),
                title: 'Reaction time below human floor',
                evidence: [
                    'reaction_ms' => round($reaction, 2),
                    'human_floor_ms' => $impossibleReactionMs,
                    'event' => $event->eventName,
                ],
                surface: $event->surface,
                userId: $event->userId,
            );
        }

        $perMinute = $context->bump("burst:{$actor}:".$event->occurredAt->format('YmdHi'), 120);
        if ($perMinute > $burstPerMinute) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::SPEED_HACK,
                severity: SecuritySeverity::HIGH,
                confidence: Statistics::confidenceFromRatio((float) $perMinute, (float) $burstPerMinute),
                title: 'Action rate exceeds human throughput',
                evidence: [
                    'actions_this_minute' => $perMinute,
                    'limit' => $burstPerMinute,
                    'surface' => $event->surface,
                ],
                surface: $event->surface,
                userId: $event->userId,
            );
        }

        return $signals;
    }

    /**
     * @return list<DetectedSignal>
     */
    private function inspectCadence(
        SecurityEventData $event,
        SecurityContext $context,
        string $actor,
        int $minSamples,
        float $jitterFloorMs,
    ): array {
        $bucket = "cadence:{$actor}:{$event->surface}";
        $lastKey = "last:{$actor}:{$event->surface}";

        $nowMs = (float) $event->occurredAt->getTimestampMs();
        $previous = $context->samples($lastKey);
        $context->pushSample($lastKey, $nowMs, 1, 3600);

        if ($previous === []) {
            return [];
        }

        $intervalMs = $nowMs - $previous[0];
        if ($intervalMs <= 0 || $intervalMs > 60000) {
            return [];
        }

        $intervals = $context->pushSample($bucket, $intervalMs, 40, 3600);
        if (count($intervals) < $minSamples) {
            return [];
        }

        $signals = [];
        $stdDev = Statistics::stdDev($intervals);
        $mean = Statistics::mean($intervals);

        // A human never produces near-identical inter-action gaps at length.
        if ($stdDev < $jitterFloorMs) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::AUTO_CLICKER,
                severity: SecuritySeverity::HIGH,
                confidence: Statistics::confidenceBelowFloor($stdDev, $jitterFloorMs),
                title: 'Machine-regular action intervals',
                evidence: [
                    'samples' => count($intervals),
                    'mean_interval_ms' => round($mean, 2),
                    'std_dev_ms' => round($stdDev, 2),
                    'jitter_floor_ms' => $jitterFloorMs,
                ],
                surface: $event->surface,
                userId: $event->userId,
            );
        }

        $cv = Statistics::coefficientOfVariation($intervals);
        if ($cv > 0 && $cv < 0.02) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::MACRO_BEHAVIOR,
                severity: SecuritySeverity::HIGH,
                confidence: min(95.0, 60.0 + ((0.02 - $cv) / 0.02) * 35.0),
                title: 'Repeating macro cadence',
                evidence: [
                    'coefficient_of_variation' => round($cv, 5),
                    'samples' => count($intervals),
                ],
                surface: $event->surface,
                userId: $event->userId,
            );
        }

        if ($this->hasPerfectPeriodicity($intervals)) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::PERFECT_TIMING,
                severity: SecuritySeverity::MEDIUM,
                confidence: 72.0,
                title: 'Repeated perfect timing',
                evidence: [
                    'samples' => count($intervals),
                    'mean_interval_ms' => round($mean, 2),
                ],
                surface: $event->surface,
                userId: $event->userId,
            );
        }

        return $signals;
    }

    /**
     * True when a majority of intervals land on the exact same millisecond.
     *
     * @param  list<float>  $intervals
     */
    private function hasPerfectPeriodicity(array $intervals): bool
    {
        $rounded = array_map(static fn (float $value): int => (int) round($value), $intervals);
        $counts = array_count_values($rounded);
        if ($counts === []) {
            return false;
        }

        return max($counts) >= (int) ceil(count($intervals) * 0.75);
    }

    private function reactionTimeMs(SecurityEventData $event): ?float
    {
        foreach (['reaction_ms', 'reaction_time_ms', 'response_ms', 'answer_time_ms', 'elapsed_ms'] as $key) {
            $value = $event->get($key);
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        foreach (['reaction_seconds', 'response_seconds', 'answer_time'] as $key) {
            $value = $event->get($key);
            if (is_numeric($value)) {
                return (float) $value * 1000;
            }
        }

        return null;
    }
}

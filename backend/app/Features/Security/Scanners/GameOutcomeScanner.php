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
 * Statistical integrity checks across every registered game plugin: Lucky
 * Wheel, Lucky Dice, Red Envelope, Treasure Box, Slot Machine, Lucky Number,
 * Quiz, Rock Paper Scissors and Ludo.
 *
 * The scanner is game-agnostic on purpose — it reasons about wagers, payouts,
 * streaks, outcome distribution and accuracy, so a tenth game is protected the
 * moment it emits events.
 */
final class GameOutcomeScanner implements Scanner
{
    public function key(): string
    {
        return 'game_outcome';
    }

    public function supports(SecurityEventData $event): bool
    {
        return $event->surface === SecuritySurface::GAMES;
    }

    public function scan(SecurityEventData $event, SecurityContext $context): array
    {
        $thresholds = (array) config('security_platform.thresholds.game', []);
        $slug = $this->gameSlug($event);
        $actor = $context->actorKey();
        $scope = "{$actor}:{$slug}";

        $signals = [];
        $signals = array_merge($signals, $this->inspectStreak($event, $context, $scope, $thresholds));
        $signals = array_merge($signals, $this->inspectRtp($event, $context, $scope, $slug, $thresholds));
        $signals = array_merge($signals, $this->inspectOutcomeDistribution($event, $context, $scope, $thresholds));
        $signals = array_merge($signals, $this->inspectAccuracy($event, $context, $scope, $thresholds));
        $signals = array_merge($signals, $this->inspectPayoutSanity($event));

        return $signals;
    }

    /**
     * @param  array<string, mixed>  $thresholds
     * @return list<DetectedSignal>
     */
    private function inspectStreak(
        SecurityEventData $event,
        SecurityContext $context,
        string $scope,
        array $thresholds,
    ): array {
        $outcome = $this->outcome($event);
        if ($outcome === null) {
            return [];
        }

        $maxStreak = (int) ($thresholds['max_win_streak'] ?? 12);
        $key = "streak:{$scope}";

        if ($outcome !== 'win') {
            $context->pushSample($key, 0.0, 1, 86400);

            return [];
        }

        $current = $context->samples($key);
        $streak = (int) (($current[0] ?? 0) + 1);
        $context->pushSample($key, (float) $streak, 1, 86400);

        if ($streak <= $maxStreak) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::IMPOSSIBLE_STREAK,
            severity: SecuritySeverity::HIGH,
            confidence: Statistics::confidenceFromRatio((float) $streak, (float) $maxStreak),
            title: 'Improbable consecutive win streak',
            evidence: [
                'streak' => $streak,
                'limit' => $maxStreak,
                'game' => $this->gameSlug($event),
            ],
            surface: SecuritySurface::GAMES,
            userId: $event->userId,
        )];
    }

    /**
     * Return-to-player above 1.0 sustained over a meaningful sample means the
     * player is extracting more than the game can pay.
     *
     * @param  array<string, mixed>  $thresholds
     * @return list<DetectedSignal>
     */
    private function inspectRtp(
        SecurityEventData $event,
        SecurityContext $context,
        string $scope,
        string $slug,
        array $thresholds,
    ): array {
        $wagered = $this->wagered($event);
        $won = $this->won($event);
        if ($wagered <= 0 && $won <= 0) {
            return [];
        }

        $minSamples = (int) ($thresholds['min_rtp_samples'] ?? 40);
        $maxRtp = (float) ($thresholds['max_rtp'] ?? 1.15);

        $rounds = $context->bump("rtp:n:{$scope}", 86400);
        $totalWagered = $context->bump("rtp:w:{$scope}", 86400, (int) round($wagered));
        $totalWon = $context->bump("rtp:p:{$scope}", 86400, (int) round($won));

        if ($rounds < $minSamples || $totalWagered <= 0) {
            return [];
        }

        $rtp = $totalWon / $totalWagered;
        if ($rtp <= $maxRtp) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::IMPOSSIBLE_RTP,
            severity: SecuritySeverity::CRITICAL,
            confidence: Statistics::confidenceFromRatio($rtp, $maxRtp),
            title: 'Return-to-player above payout ceiling',
            evidence: [
                'rtp' => round($rtp, 4),
                'ceiling' => $maxRtp,
                'rounds' => $rounds,
                'total_wagered' => $totalWagered,
                'total_won' => $totalWon,
                'game' => $slug,
            ],
            surface: SecuritySurface::GAMES,
            userId: $event->userId,
        )];
    }

    /**
     * Chi-square test on discrete outcomes (dice faces, wheel sectors, reels).
     *
     * @param  array<string, mixed>  $thresholds
     * @return list<DetectedSignal>
     */
    private function inspectOutcomeDistribution(
        SecurityEventData $event,
        SecurityContext $context,
        string $scope,
        array $thresholds,
    ): array {
        $face = $this->discreteOutcome($event);
        if ($face === null) {
            return [];
        }

        $minSamples = (int) ($thresholds['dice_chi_square_samples'] ?? 60);
        $bucket = "dist:{$scope}";

        $samples = $context->pushSample($bucket, (float) $face, 400, 86400);
        if (count($samples) < $minSamples) {
            return [];
        }

        $observed = array_count_values(array_map('intval', $samples));
        if (count($observed) < 2) {
            // Every roll landed on one face — conclusive on its own.
            return [DetectedSignal::make(
                signal: SecuritySignal::RNG_MANIPULATION,
                severity: SecuritySeverity::CRITICAL,
                confidence: 95.0,
                title: 'Every outcome identical across sample',
                evidence: [
                    'samples' => count($samples),
                    'outcome' => array_key_first($observed),
                    'game' => $this->gameSlug($event),
                ],
                surface: SecuritySurface::GAMES,
                userId: $event->userId,
            )];
        }

        $chi = Statistics::chiSquareUniform($observed);
        $critical = Statistics::chiSquareCritical(count($observed) - 1);
        if ($chi <= $critical) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::RNG_MANIPULATION,
            severity: SecuritySeverity::CRITICAL,
            confidence: Statistics::confidenceFromRatio($chi, $critical),
            title: 'Outcome distribution fails uniformity test',
            evidence: [
                'chi_square' => round($chi, 4),
                'critical_value_p001' => round($critical, 4),
                'samples' => count($samples),
                'distribution' => $observed,
                'game' => $this->gameSlug($event),
            ],
            surface: SecuritySurface::GAMES,
            userId: $event->userId,
        )];
    }

    /**
     * Quiz-style accuracy that no honest player sustains.
     *
     * @param  array<string, mixed>  $thresholds
     * @return list<DetectedSignal>
     */
    private function inspectAccuracy(
        SecurityEventData $event,
        SecurityContext $context,
        string $scope,
        array $thresholds,
    ): array {
        $isCorrect = $event->get('is_correct') ?? $event->get('correct');
        if ($isCorrect === null) {
            return [];
        }

        $minSamples = (int) ($thresholds['min_quiz_samples'] ?? 25);
        $maxAccuracy = (float) ($thresholds['max_quiz_accuracy'] ?? 0.98);

        $answered = $context->bump("acc:n:{$scope}", 86400);
        $correct = $context->bump(
            "acc:c:{$scope}",
            86400,
            (filter_var($isCorrect, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false) ? 1 : 0,
        );

        if ($answered < $minSamples) {
            return [];
        }

        $accuracy = $correct / $answered;
        if ($accuracy <= $maxAccuracy) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::IMPOSSIBLE_ACCURACY,
            severity: SecuritySeverity::HIGH,
            confidence: Statistics::confidenceFromRatio($accuracy, $maxAccuracy),
            title: 'Answer accuracy beyond human ceiling',
            evidence: [
                'accuracy' => round($accuracy, 4),
                'ceiling' => $maxAccuracy,
                'answered' => $answered,
                'correct' => $correct,
            ],
            surface: SecuritySurface::GAMES,
            userId: $event->userId,
        )];
    }

    /**
     * A single payout that dwarfs its own stake beyond any configured
     * multiplier indicates the outcome was authored client-side.
     *
     * @return list<DetectedSignal>
     */
    private function inspectPayoutSanity(SecurityEventData $event): array
    {
        $wagered = $this->wagered($event);
        $won = $this->won($event);
        $maxMultiplier = (float) ($event->get('max_multiplier') ?? 0);

        if ($wagered <= 0 || $won <= 0 || $maxMultiplier <= 0) {
            return [];
        }

        $multiplier = $won / $wagered;
        if ($multiplier <= $maxMultiplier) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::IMPOSSIBLE_OUTCOME,
            severity: SecuritySeverity::CRITICAL,
            confidence: Statistics::confidenceFromRatio($multiplier, $maxMultiplier),
            title: 'Payout exceeds the game\'s maximum multiplier',
            evidence: [
                'multiplier' => round($multiplier, 4),
                'declared_max' => $maxMultiplier,
                'wagered' => $wagered,
                'won' => $won,
                'game' => $this->gameSlug($event),
            ],
            surface: SecuritySurface::GAMES,
            userId: $event->userId,
        )];
    }

    private function gameSlug(SecurityEventData $event): string
    {
        foreach (['game_slug', 'gameSlug', 'slug', 'plugin'] as $key) {
            $value = $event->get($key);
            if (is_string($value) && $value !== '') {
                return strtolower($value);
            }
        }

        return 'unknown';
    }

    private function outcome(SecurityEventData $event): ?string
    {
        $raw = $event->get('outcome') ?? $event->get('result') ?? $event->get('status');
        if (is_string($raw) && $raw !== '') {
            $lower = strtolower($raw);
            if (str_contains($lower, 'win') || $lower === 'won') {
                return 'win';
            }
            if (str_contains($lower, 'los') || str_contains($lower, 'fail')) {
                return 'loss';
            }
            if (str_contains($lower, 'draw') || str_contains($lower, 'tie')) {
                return 'draw';
            }
        }

        // Fall back to the money: a payout larger than the stake is a win.
        $wagered = $this->wagered($event);
        $won = $this->won($event);
        if ($wagered <= 0 && $won <= 0) {
            return null;
        }

        return $won > $wagered ? 'win' : 'loss';
    }

    private function discreteOutcome(SecurityEventData $event): ?int
    {
        foreach (['dice_value', 'face', 'sector_index', 'sector', 'reel_index', 'drawn_number', 'winning_number', 'symbol_index'] as $key) {
            $value = $event->get($key);
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private function wagered(SecurityEventData $event): float
    {
        foreach (['bet_coins', 'bet', 'stake', 'wager', 'ticket_price', 'cost'] as $key) {
            $value = $event->numeric($key, -1);
            if ($value >= 0) {
                return $value;
            }
        }

        return 0.0;
    }

    private function won(SecurityEventData $event): float
    {
        foreach (['reward_coins', 'reward', 'payout', 'prize', 'win_amount', 'winnings'] as $key) {
            $value = $event->numeric($key, -1);
            if ($value >= 0) {
                return $value;
            }
        }

        return 0.0;
    }
}

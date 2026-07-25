<?php

namespace App\Features\Games\Plugins\LuckyNumber\Support;

use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberConfig;
use App\Features\Games\Services\GameRng;

/**
 * Server-side draw / pick / settlement math.
 * Clients never compute outcomes — they only animate this result.
 */
final class LuckyNumberDrawEngine
{
    public function __construct(private readonly GameRng $rng) {}

    /**
     * @return array{winning_numbers: list<int>, rolls: list<int>, audit: array<string, mixed>}
     */
    public function drawNumbers(int $min, int $max, int $count, string $serverSeed): array
    {
        if ($min > $max) {
            throw new \InvalidArgumentException('min must be <= max.');
        }

        $span = ($max - $min) + 1;
        $count = max(1, min($count, $span));

        $pool = range($min, $max);
        $winning = [];
        $rolls = [];

        for ($i = 0; $i < $count; $i++) {
            $roll = $this->rng->int(0, count($pool) - 1);
            $rolls[] = $roll;
            $winning[] = (int) $pool[$roll];
            array_splice($pool, $roll, 1);
        }

        sort($winning);

        return [
            'winning_numbers' => $winning,
            'rolls' => $rolls,
            'audit' => [
                'min' => $min,
                'max' => $max,
                'count' => $count,
                'server_seed_hash' => hash('sha256', $serverSeed),
                'drawn_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Resolve picks for lucky_pick / auto_pick.
     *
     * @return list<int>
     */
    public function pickNumbers(int $min, int $max, int $count): array
    {
        if ($min > $max) {
            throw new \InvalidArgumentException('min must be <= max.');
        }

        $span = ($max - $min) + 1;
        $count = max(1, min($count, $span));

        return $this->rng->sampleUnique($min, $max, $count);
    }

    /**
     * Calculate reward from picks vs winning numbers.
     *
     * @param  list<int>  $picked
     * @param  list<int>  $winning
     * @param  array<string, mixed>  $multipliers
     * @return array{
     *   match_count: int,
     *   multiplier: float,
     *   reward_coins: int,
     *   is_win: bool,
     *   is_jackpot: bool,
     *   matched: list<int>
     * }
     */
    public function settle(
        array $picked,
        array $winning,
        int $betAmount,
        array $multipliers,
        float $vipMultiplier = 1.0,
        bool $applyVip = false,
        string $gameMode = 'single_number',
    ): array {
        $pickedSet = array_values(array_unique(array_map('intval', $picked)));
        $winningSet = array_values(array_unique(array_map('intval', $winning)));
        sort($pickedSet);
        sort($winningSet);

        $matched = array_values(array_intersect($pickedSet, $winningSet));
        sort($matched);
        $matchCount = count($matched);

        $maxNumber = $winningSet === [] ? 10 : max($winningSet);
        $defaultExact = (float) max(2, $maxNumber - 1);

        $isFullMatch = $matchCount > 0
            && $matchCount === count($winningSet)
            && $matchCount === count($pickedSet);

        $isJackpot = $gameMode === LuckyNumberConfig::MODE_PROGRESSIVE_JACKPOT && $isFullMatch;

        $multiplier = 0.0;
        if ($matchCount > 0) {
            if ($gameMode === LuckyNumberConfig::MODE_SINGLE_NUMBER
                || (count($pickedSet) === 1 && count($winningSet) === 1)) {
                $multiplier = (float) ($multipliers['exact']
                    ?? $multipliers['single']
                    ?? $defaultExact);
            } else {
                $key = 'match_'.$matchCount;
                $multiplier = (float) ($multipliers[$key]
                    ?? $multipliers[(string) $matchCount]
                    ?? ($isFullMatch ? ($multipliers['exact'] ?? $multipliers['full'] ?? $defaultExact) : 0));
            }
        }

        if ($applyVip && $vipMultiplier > 1) {
            $multiplier *= $vipMultiplier;
        }

        // Progressive jackpot full-match reward comes from the jackpot pool in the service.
        $rewardCoins = ($isJackpot || $multiplier <= 0)
            ? 0
            : (int) floor($betAmount * $multiplier);

        return [
            'match_count' => $matchCount,
            'multiplier' => round($multiplier, 4),
            'reward_coins' => $rewardCoins,
            'is_win' => $matchCount > 0 && ($rewardCoins > 0 || $isJackpot),
            'is_jackpot' => $isJackpot,
            'matched' => $matched,
        ];
    }
}

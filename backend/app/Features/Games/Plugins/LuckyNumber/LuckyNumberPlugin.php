<?php

namespace App\Features\Games\Plugins\LuckyNumber;

use App\Features\Games\Core\Contracts\GamePlugin;
use App\Features\Games\Engines\EngineOutcome;
use App\Features\Games\Engines\LuckyNumberEngine;
use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberConfig;
use App\Features\Users\Models\User;

/**
 * Lucky Number plugin — generic engine resolve remains for /games/play.
 * Dedicated production play flow lives in LuckyNumberService.
 */
final class LuckyNumberPlugin implements GamePlugin
{
    public function __construct(private readonly LuckyNumberEngine $engine) {}

    public function slug(): string
    {
        return Game::SLUG_LUCKY_NUMBER;
    }

    public function displayName(): string
    {
        return 'Lucky Number';
    }

    public function supportsMultiplayer(): bool
    {
        return false;
    }

    public function resolve(User $user, Game $game, int $betCoins, array $input): ?EngineOutcome
    {
        return $this->engine->resolve($user, $game, $betCoins, $input);
    }

    public function defaultConfig(): array
    {
        return [
            'min_bet_coins' => 10,
            'max_bet_coins' => 10000,
            'default_bet_coins' => 50,
            'chip_amounts' => [10, 50, 100, 500, 1000],
            'modes_enabled' => LuckyNumberConfig::MODES,
            'number_ranges' => LuckyNumberConfig::RANGE_PRESETS,
            'mode_ranges' => [
                LuckyNumberConfig::MODE_SINGLE_NUMBER => ['min' => 1, 'max' => 10],
                LuckyNumberConfig::MODE_MULTIPLE_NUMBERS => ['min' => 1, 'max' => 50],
                LuckyNumberConfig::MODE_LUCKY_PICK => ['min' => 1, 'max' => 50],
                LuckyNumberConfig::MODE_QUICK_BET => ['min' => 1, 'max' => 10],
                LuckyNumberConfig::MODE_VIP_LUCKY_NUMBERS => ['min' => 1, 'max' => 100],
                LuckyNumberConfig::MODE_DAILY_LUCKY_NUMBER => ['min' => 1, 'max' => 20],
                LuckyNumberConfig::MODE_PROGRESSIVE_JACKPOT => ['min' => 1, 'max' => 50],
            ],
            'default_min_number' => 1,
            'default_max_number' => 10,
            'max_picks' => 5,
            'draw_count' => 5,
            'reward_multipliers' => [
                'single' => 9,
                'exact' => 9,
                'match_1' => 2,
                'match_2' => 5,
                'match_3' => 15,
                'match_4' => 50,
                'match_5' => 200,
            ],
            'rtp_percent' => 96.0,
            'house_edge_percent' => 4.0,
            'max_tickets_per_day' => 200,
            'cooldown_seconds' => 0,
            'progressive_contribution_percent' => 1.0,
            'jackpot_enabled' => true,
            'bonus_enabled' => true,
            'vip_reward_multiplier' => 1.5,
            'draw_interval_seconds' => 300,
            'bonus_probabilities' => [
                'lucky_pick_bonus' => 8,
                'lucky_pick_bonus_multiplier' => 1.5,
                'streak_reward' => 5,
                'streak_reward_multiplier' => 2,
                'daily_bonus' => 3,
                'daily_bonus_multiplier' => 2,
                'vip_bonus' => 10,
                'vip_bonus_multiplier' => 1.5,
                'referral_bonus' => 2,
                'referral_bonus_multiplier' => 1.25,
                'mystery_bonus' => 2,
                'mystery_bonus_multiplier' => 5,
            ],
        ];
    }
}

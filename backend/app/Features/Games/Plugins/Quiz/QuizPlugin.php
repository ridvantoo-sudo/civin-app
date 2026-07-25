<?php

namespace App\Features\Games\Plugins\Quiz;

use App\Features\Games\Core\Contracts\GamePlugin;
use App\Features\Games\Engines\EngineOutcome;
use App\Features\Games\Engines\QuizEngine;
use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\Quiz\Models\QuizConfig;
use App\Features\Users\Models\User;

/**
 * Quiz plugin. Legacy `/games/play` still delegates to QuizEngine.
 * The production multiplayer flow lives in QuizService behind `/games/quiz/*`.
 */
final class QuizPlugin implements GamePlugin
{
    public function __construct(private readonly QuizEngine $engine) {}

    public function slug(): string
    {
        return Game::SLUG_QUIZ;
    }

    public function displayName(): string
    {
        return 'Quiz';
    }

    public function supportsMultiplayer(): bool
    {
        return true;
    }

    public function resolve(User $user, Game $game, int $betCoins, array $input): ?EngineOutcome
    {
        return $this->engine->resolve($user, $game, $betCoins, $input);
    }

    public function defaultConfig(): array
    {
        return [
            'min_bet_coins' => 10,
            'max_bet_coins' => 5000,
            'default_bet_coins' => 10,
            'default_question_count' => 10,
            'max_question_count' => 50,
            'default_time_per_question' => 15,
            'min_time_per_question' => 5,
            'max_time_per_question' => 120,
            'default_winner_count' => 3,
            'max_winner_count' => 50,
            'max_players' => 100,
            'max_created_per_day' => 50,
            'cooldown_seconds' => 0,
            'vip_reward_multiplier' => 1.25,
            'modes_enabled' => QuizConfig::MODES,
            'question_types_enabled' => QuizConfig::QUESTION_TYPES,
            'difficulties_enabled' => QuizConfig::DIFFICULTIES,
            'reward_types_enabled' => QuizConfig::REWARD_TYPES,
            'visibilities_enabled' => QuizConfig::VISIBILITIES,
            'eligibilities_enabled' => QuizConfig::ELIGIBILITIES,
            'scoring_rules' => QuizConfig::DEFAULT_SCORING,
        ];
    }
}

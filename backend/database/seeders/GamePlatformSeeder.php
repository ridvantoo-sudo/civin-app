<?php

namespace Database\Seeders;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlugin;
use App\Features\Games\Models\LotteryRound;
use App\Features\Games\Models\QuizQuestion;
use Illuminate\Database\Seeder;

class GamePlatformSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            [
                'slug' => Game::SLUG_LUCKY_WHEEL,
                'name' => 'Lucky Wheel',
                'description' => 'Spin the wheel and multiply your coins.',
                'icon' => 'https://cdn.civin.app/games/lucky_wheel.png',
                'category' => 'solo',
                'min_bet_coins' => 10,
                'max_bet_coins' => 5000,
                'house_edge_bps' => 500,
                'supports_multiplayer' => false,
                'sort_order' => 1,
                'config' => [
                    'segments' => [
                        ['label' => 'x0', 'multiplier' => 0, 'weight' => 40],
                        ['label' => 'x1', 'multiplier' => 1, 'weight' => 30],
                        ['label' => 'x2', 'multiplier' => 2, 'weight' => 18],
                        ['label' => 'x5', 'multiplier' => 5, 'weight' => 8],
                        ['label' => 'x10', 'multiplier' => 10, 'weight' => 4],
                    ],
                ],
            ],
            [
                'slug' => Game::SLUG_LUCKY_DICE,
                'name' => 'Lucky Dice',
                'description' => 'Guess the dice roll or high/low.',
                'icon' => 'https://cdn.civin.app/games/lucky_dice.png',
                'category' => 'solo',
                'min_bet_coins' => 10,
                'max_bet_coins' => 5000,
                'house_edge_bps' => 400,
                'supports_multiplayer' => false,
                'sort_order' => 2,
                'config' => null,
            ],
            [
                'slug' => Game::SLUG_LUCKY_NUMBER,
                'name' => 'Lucky Number',
                'description' => 'Pick a number and hope it matches.',
                'icon' => 'https://cdn.civin.app/games/lucky_number.png',
                'category' => 'solo',
                'min_bet_coins' => 10,
                'max_bet_coins' => 5000,
                'house_edge_bps' => 600,
                'supports_multiplayer' => false,
                'sort_order' => 3,
                'config' => ['max_number' => 10],
            ],
            [
                'slug' => Game::SLUG_TREASURE_BOX,
                'name' => 'Treasure Box',
                'description' => 'Open a mystery box for coin rewards.',
                'icon' => 'https://cdn.civin.app/games/treasure_box.png',
                'category' => 'solo',
                'min_bet_coins' => 20,
                'max_bet_coins' => 10000,
                'house_edge_bps' => 700,
                'supports_multiplayer' => false,
                'sort_order' => 4,
                'config' => [
                    'tiers' => [
                        'bronze' => ['cost_multiplier' => 1, 'min_mult' => 0, 'max_mult' => 2],
                        'silver' => ['cost_multiplier' => 2, 'min_mult' => 0, 'max_mult' => 4],
                        'gold' => ['cost_multiplier' => 5, 'min_mult' => 0, 'max_mult' => 10],
                    ],
                ],
            ],
            [
                'slug' => Game::SLUG_RED_ENVELOPE,
                'name' => 'Red Envelope',
                'description' => 'Send and claim lucky red envelopes.',
                'icon' => 'https://cdn.civin.app/games/red_envelope.png',
                'category' => 'social',
                'min_bet_coins' => 10,
                'max_bet_coins' => 50000,
                'house_edge_bps' => 0,
                'supports_multiplayer' => false,
                'sort_order' => 5,
                'config' => ['expires_hours' => 24],
            ],
            [
                'slug' => Game::SLUG_QUIZ,
                'name' => 'Quiz',
                'description' => 'Answer trivia questions to win coins.',
                'icon' => 'https://cdn.civin.app/games/quiz.png',
                'category' => 'solo',
                'min_bet_coins' => 10,
                'max_bet_coins' => 1000,
                'house_edge_bps' => 0,
                'supports_multiplayer' => false,
                'sort_order' => 6,
                'config' => null,
            ],
            [
                'slug' => Game::SLUG_LOTTERY,
                'name' => 'Lottery',
                'description' => 'Buy tickets and match the drawn numbers.',
                'icon' => 'https://cdn.civin.app/games/lottery.png',
                'category' => 'solo',
                'min_bet_coins' => 50,
                'max_bet_coins' => 50,
                'house_edge_bps' => 800,
                'supports_multiplayer' => false,
                'sort_order' => 7,
                'config' => [
                    'ticket_price' => 50,
                    'max_number' => 49,
                    'picks' => 6,
                    'instant' => false,
                    'match_multipliers' => [
                        3 => 2,
                        4 => 10,
                        5 => 50,
                        6 => 500,
                    ],
                ],
            ],
            [
                'slug' => Game::SLUG_SLOT_MACHINE,
                'name' => 'Slot Machine',
                'description' => 'Spin the reels for jackpot prizes.',
                'icon' => 'https://cdn.civin.app/games/slot_machine.png',
                'category' => 'solo',
                'min_bet_coins' => 10,
                'max_bet_coins' => 5000,
                'house_edge_bps' => 900,
                'supports_multiplayer' => false,
                'sort_order' => 8,
                'config' => [
                    'symbols' => ['cherry', 'lemon', 'bell', 'star', 'seven'],
                ],
            ],
            [
                'slug' => Game::SLUG_ROCK_PAPER_SCISSORS,
                'name' => 'Rock Paper Scissors',
                'description' => 'Challenge the house or another player.',
                'icon' => 'https://cdn.civin.app/games/rps.png',
                'category' => 'versus',
                'min_bet_coins' => 10,
                'max_bet_coins' => 5000,
                'house_edge_bps' => 0,
                'supports_multiplayer' => true,
                'sort_order' => 9,
                'config' => null,
            ],
            [
                'slug' => Game::SLUG_LUDO,
                'name' => 'Ludo',
                'description' => 'Turn-based race to the finish line.',
                'icon' => 'https://cdn.civin.app/games/ludo.png',
                'category' => 'versus',
                'min_bet_coins' => 10,
                'max_bet_coins' => 5000,
                'house_edge_bps' => 0,
                'supports_multiplayer' => true,
                'sort_order' => 10,
                'config' => ['finish_line' => 50],
            ],
        ];

        foreach ($games as $attributes) {
            Game::query()->updateOrCreate(
                ['slug' => $attributes['slug']],
                array_merge($attributes, ['status' => Game::STATUS_ACTIVE]),
            );
        }

        $questions = [
            [
                'question' => 'What is 2 + 2?',
                'options' => ['3', '4', '5', '22'],
                'correct_index' => 1,
                'difficulty' => 'easy',
                'bet_coins' => 10,
                'reward_coins' => 20,
            ],
            [
                'question' => 'Which planet is known as the Red Planet?',
                'options' => ['Venus', 'Jupiter', 'Mars', 'Mercury'],
                'correct_index' => 2,
                'difficulty' => 'easy',
                'bet_coins' => 10,
                'reward_coins' => 25,
            ],
            [
                'question' => 'How many continents are there on Earth?',
                'options' => ['5', '6', '7', '8'],
                'correct_index' => 2,
                'difficulty' => 'easy',
                'bet_coins' => 10,
                'reward_coins' => 20,
            ],
            [
                'question' => 'What is the capital of France?',
                'options' => ['Berlin', 'Madrid', 'Rome', 'Paris'],
                'correct_index' => 3,
                'difficulty' => 'easy',
                'bet_coins' => 10,
                'reward_coins' => 20,
            ],
            [
                'question' => 'Which gas do plants absorb from the atmosphere?',
                'options' => ['Oxygen', 'Nitrogen', 'Carbon dioxide', 'Hydrogen'],
                'correct_index' => 2,
                'difficulty' => 'medium',
                'bet_coins' => 15,
                'reward_coins' => 40,
            ],
        ];

        foreach ($questions as $question) {
            $existing = QuizQuestion::query()
                ->where('question', $question['question'])
                ->first();

            if ($existing === null) {
                QuizQuestion::query()->create(array_merge($question, [
                    'status' => QuizQuestion::STATUS_ACTIVE,
                ]));
            } else {
                $existing->forceFill(array_merge($question, [
                    'status' => QuizQuestion::STATUS_ACTIVE,
                ]))->save();
            }
        }

        foreach (Game::query()->orderBy('sort_order')->get() as $game) {
            GamePlugin::query()->updateOrCreate(
                ['slug' => $game->slug],
                [
                    'name' => $game->name,
                    'version' => '1.0.0',
                    'enabled' => $game->status === Game::STATUS_ACTIVE,
                    'config' => $game->config,
                    'reward_config' => null,
                    'coin_limit_per_day' => 100000,
                    'coin_limit_per_play' => $game->max_bet_coins,
                ],
            );
        }

        $lottery = Game::query()->where('slug', Game::SLUG_LOTTERY)->first();
        if ($lottery !== null) {
            $open = LotteryRound::query()
                ->where('game_id', $lottery->getKey())
                ->where('status', LotteryRound::STATUS_OPEN)
                ->exists();

            if (! $open) {
                LotteryRound::query()->create([
                    'game_id' => $lottery->getKey(),
                    'ticket_price' => 50,
                    'max_number' => 49,
                    'picks' => 6,
                    'status' => LotteryRound::STATUS_OPEN,
                    'closes_at' => now()->addDay(),
                ]);
            }
        }
    }
}

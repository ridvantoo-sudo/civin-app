<?php

namespace Database\Seeders;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\Quiz\Models\QuizCategory;
use App\Features\Games\Plugins\Quiz\Models\QuizConfig;
use App\Features\Games\Plugins\Quiz\Models\QuizQuestion;
use App\Features\Games\Plugins\Quiz\Models\QuizQuestionOption;
use Illuminate\Database\Seeder;

class QuizSeeder extends Seeder
{
    public function run(): void
    {
        $game = Game::query()->where('slug', Game::SLUG_QUIZ)->first();

        $config = QuizConfig::query()->updateOrCreate(
            ['slug' => 'default'],
            [
                'game_id' => $game?->getKey(),
                'name' => 'Quiz Default',
                'description' => 'Default production Quiz configuration.',
                'version' => 1,
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
                'modes_enabled' => QuizConfig::MODES,
                'question_types_enabled' => QuizConfig::QUESTION_TYPES,
                'difficulties_enabled' => QuizConfig::DIFFICULTIES,
                'reward_types_enabled' => QuizConfig::REWARD_TYPES,
                'visibilities_enabled' => QuizConfig::VISIBILITIES,
                'eligibilities_enabled' => QuizConfig::ELIGIBILITIES,
                'scoring_rules' => QuizConfig::DEFAULT_SCORING,
                'vip_reward_multiplier' => 1.25,
                'enabled' => true,
                'status' => QuizConfig::STATUS_ACTIVE,
                'is_default' => true,
                'metadata' => ['source' => 'QuizSeeder'],
            ],
        );

        if ($config->is_default) {
            QuizConfig::query()
                ->where('id', '!=', $config->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $categoryNames = [
            'technology' => 'Technology',
            'sports' => 'Sports',
            'movies' => 'Movies',
            'music' => 'Music',
            'history' => 'History',
            'science' => 'Science',
            'geography' => 'Geography',
            'gaming' => 'Gaming',
            'general_knowledge' => 'General Knowledge',
        ];

        $categories = [];
        $sort = 0;
        foreach ($categoryNames as $slug => $name) {
            $categories[$slug] = QuizCategory::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => $name.' quiz category',
                    'icon' => $slug,
                    'is_custom' => false,
                    'status' => QuizCategory::STATUS_ACTIVE,
                    'sort_order' => $sort++,
                ],
            );
        }

        $general = $categories['general_knowledge'];
        $science = $categories['science'];
        $geography = $categories['geography'];

        $samples = [
            [
                'question' => 'How many continents are there on Earth?',
                'category' => $geography,
                'options' => ['5', '6', '7', '8'],
                'correct_index' => 2,
                'difficulty' => 'easy',
            ],
            [
                'question' => 'What is the capital of France?',
                'category' => $geography,
                'options' => ['Berlin', 'Madrid', 'Rome', 'Paris'],
                'correct_index' => 3,
                'difficulty' => 'easy',
            ],
            [
                'question' => 'Which gas do plants absorb from the atmosphere?',
                'category' => $science,
                'options' => ['Oxygen', 'Nitrogen', 'Carbon dioxide', 'Hydrogen'],
                'correct_index' => 2,
                'difficulty' => 'medium',
            ],
        ];

        foreach ($samples as $sample) {
            $question = QuizQuestion::query()->updateOrCreate(
                ['question' => $sample['question']],
                [
                    'category_id' => $sample['category']->getKey(),
                    'config_id' => $config->getKey(),
                    'type' => 'multiple_choice',
                    'language' => 'en',
                    'prompt' => $sample['question'],
                    'options' => $sample['options'],
                    'correct_index' => $sample['correct_index'],
                    'difficulty' => $sample['difficulty'],
                    'bet_coins' => 10,
                    'reward_coins' => 20,
                    'status' => QuizQuestion::STATUS_ACTIVE,
                    'approval_status' => QuizQuestion::APPROVAL_APPROVED,
                    'approved_at' => now(),
                ],
            );

            foreach ($sample['options'] as $index => $label) {
                QuizQuestionOption::query()->updateOrCreate(
                    [
                        'question_id' => $question->getKey(),
                        'option_index' => $index,
                    ],
                    [
                        'label' => $label,
                        'is_correct' => $index === $sample['correct_index'],
                    ],
                );
            }
        }

        // Ensure any other platform-seeded questions are playable.
        QuizQuestion::query()
            ->whereNull('approval_status')
            ->orWhere('approval_status', '')
            ->update([
                'approval_status' => QuizQuestion::APPROVAL_APPROVED,
                'type' => 'multiple_choice',
                'language' => 'en',
                'category_id' => $general->getKey(),
            ]);

        QuizQuestion::query()
            ->whereNull('category_id')
            ->update(['category_id' => $general->getKey()]);
    }
}

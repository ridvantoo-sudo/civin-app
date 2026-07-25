<?php

namespace Tests\Unit\Quiz;

use App\Features\Games\Plugins\Quiz\Models\QuizPlayer;
use App\Features\Games\Plugins\Quiz\Models\QuizQuestion;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Games\Plugins\Quiz\Support\QuizScoringEngine;
use PHPUnit\Framework\TestCase;

class QuizScoringEngineTest extends TestCase
{
    public function test_wrong_answer_scores_zero(): void
    {
        $engine = new QuizScoringEngine;
        $result = $engine->scoreAnswer(
            new QuizSession(['time_per_question' => 15, 'mode' => 'classic', 'scoring_rules' => []]),
            new QuizPlayer(['streak' => 0]),
            new QuizQuestion(['type' => 'multiple_choice', 'correct_index' => 0, 'options' => ['a', 'b']]),
            1,
            null,
            100,
            false,
        );

        $this->assertFalse($result['is_correct']);
        $this->assertSame(0, $result['points']);
    }

    public function test_timed_out_is_incorrect(): void
    {
        $engine = new QuizScoringEngine;
        $result = $engine->scoreAnswer(
            new QuizSession(['time_per_question' => 15, 'mode' => 'classic', 'scoring_rules' => []]),
            new QuizPlayer(['streak' => 5]),
            new QuizQuestion(['type' => 'multiple_choice', 'correct_index' => 0, 'options' => ['a', 'b']]),
            0,
            null,
            20000,
            true,
        );

        $this->assertFalse($result['is_correct']);
        $this->assertSame(0, $result['streak']);
    }

    public function test_text_answer_is_case_insensitive(): void
    {
        $engine = new QuizScoringEngine;
        $question = new QuizQuestion([
            'type' => 'text',
            'correct_index' => 0,
            'options' => ['Paris'],
        ]);

        $this->assertTrue($engine->isCorrect($question, null, 'paris'));
        $this->assertFalse($engine->isCorrect($question, null, 'London'));
    }
}

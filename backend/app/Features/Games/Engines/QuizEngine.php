<?php

namespace App\Features\Games\Engines;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\QuizQuestion;
use App\Features\Users\Models\User;
use Illuminate\Validation\ValidationException;

final class QuizEngine implements GameEngine
{
    public function slug(): string
    {
        return Game::SLUG_QUIZ;
    }

    public function resolve(User $user, Game $game, int $betCoins, array $input): EngineOutcome
    {
        $questionId = (string) ($input['question_id'] ?? '');
        $answerIndex = isset($input['answer_index']) ? (int) $input['answer_index'] : -1;

        $question = QuizQuestion::query()
            ->whereKey($questionId)
            ->where('status', QuizQuestion::STATUS_ACTIVE)
            ->first();

        if ($question === null) {
            throw ValidationException::withMessages(['input.question_id' => 'Quiz question not found.']);
        }

        $options = $question->options ?? [];
        if ($answerIndex < 0 || $answerIndex >= count($options)) {
            throw ValidationException::withMessages(['input.answer_index' => 'Invalid answer.']);
        }

        $cost = max($betCoins, (int) $question->bet_coins);
        $correct = $answerIndex === (int) $question->correct_index;
        $reward = $correct ? max($cost, (int) $question->reward_coins) + $cost : 0;

        return new EngineOutcome(
            betCoins: $cost,
            rewardCoins: $reward,
            rewardDiamonds: $correct ? 0 : 0,
            result: $correct ? GamePlay::RESULT_WON : GamePlay::RESULT_LOST,
            outcome: [
                'question_id' => $question->id,
                'correct' => $correct,
                'correct_index' => $correct ? $question->correct_index : null,
                'animation' => $correct ? 'quiz_correct' : 'quiz_wrong',
            ],
            input: [
                'question_id' => $question->id,
                'answer_index' => $answerIndex,
            ],
        );
    }
}

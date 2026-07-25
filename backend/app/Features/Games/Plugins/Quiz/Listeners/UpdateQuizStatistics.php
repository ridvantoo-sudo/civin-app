<?php

namespace App\Features\Games\Plugins\Quiz\Listeners;

use App\Features\Games\Plugins\Quiz\Events\AnswerSubmitted;
use App\Features\Games\Plugins\Quiz\Events\QuizFinished;
use App\Features\Games\Plugins\Quiz\Events\RewardGranted;
use App\Features\Games\Plugins\Quiz\Models\QuizPlayer;
use App\Features\Games\Plugins\Quiz\Models\QuizStatistic;
use Illuminate\Support\Facades\DB;

final class UpdateQuizStatistics
{
    public function handle(AnswerSubmitted|QuizFinished|RewardGranted $event): void
    {
        if ($event instanceof AnswerSubmitted) {
            $this->bump((string) $event->answer->user_id, $event->session->config_id, [
                'questions_answered' => 1,
                'correct_answers' => $event->answer->is_correct ? 1 : 0,
                'wrong_answers' => $event->answer->is_correct ? 0 : 1,
            ], (int) ($event->answer->response_ms ?? 0));

            return;
        }

        if ($event instanceof RewardGranted) {
            $coins = $event->reward->currency === 'diamonds' ? 0 : (int) $event->reward->amount;
            $diamonds = $event->reward->currency === 'diamonds' ? (int) $event->reward->amount : 0;
            $this->bump((string) $event->reward->user_id, $event->session->config_id, [
                'coins_won' => $coins,
                'diamonds_won' => $diamonds,
                'wins' => 1,
            ]);

            return;
        }

        $players = QuizPlayer::query()->where('session_id', $event->session->getKey())->get();
        foreach ($players as $player) {
            $this->bump((string) $player->user_id, $event->session->config_id, [
                'games_played' => 1,
                'total_score' => (int) $player->score,
                'highest_score' => 0,
            ], 0, (int) $player->score);
        }
    }

    /** @param array<string, int> $increments */
    private function bump(
        string $userId,
        ?string $configId,
        array $increments,
        int $responseMsSample = 0,
        int $scoreSample = 0,
    ): void {
        DB::transaction(function () use ($userId, $configId, $increments, $responseMsSample, $scoreSample): void {
            $stats = QuizStatistic::query()->firstOrCreate(
                ['user_id' => $userId, 'config_id' => $configId],
                [
                    'games_played' => 0,
                    'questions_answered' => 0,
                    'correct_answers' => 0,
                    'wrong_answers' => 0,
                    'accuracy_percent' => 0,
                    'average_response_ms' => 0,
                    'highest_score' => 0,
                    'total_score' => 0,
                    'wins' => 0,
                    'win_rate' => 0,
                    'coins_won' => 0,
                    'diamonds_won' => 0,
                ],
            );

            $locked = QuizStatistic::query()->whereKey($stats->getKey())->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }

            foreach ($increments as $column => $amount) {
                if ($column === 'highest_score') {
                    continue;
                }
                $locked->{$column} = (int) $locked->{$column} + $amount;
            }

            if ($scoreSample > 0) {
                $locked->highest_score = max((int) $locked->highest_score, $scoreSample);
            }

            $answered = max(0, (int) $locked->questions_answered);
            if ($answered > 0) {
                $locked->accuracy_percent = round(((int) $locked->correct_answers / $answered) * 100, 2);
            }

            $played = max(0, (int) $locked->games_played);
            if ($played > 0) {
                $locked->win_rate = round(((int) $locked->wins / $played) * 100, 2);
            }

            if ($responseMsSample > 0 && isset($increments['questions_answered'])) {
                $prevTotal = (float) $locked->average_response_ms * max(0, $answered - 1);
                $locked->average_response_ms = round(($prevTotal + $responseMsSample) / max(1, $answered), 2);
            }

            $locked->save();
        });
    }
}

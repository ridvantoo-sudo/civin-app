<?php

namespace App\Features\Games\Plugins\Quiz\Support;

use App\Features\Games\Plugins\Quiz\Models\QuizConfig;
use App\Features\Games\Plugins\Quiz\Models\QuizPlayer;
use App\Features\Games\Plugins\Quiz\Models\QuizQuestion;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;

/**
 * Server-side scoring only. Clients never supply points.
 */
final class QuizScoringEngine
{
    /**
     * @param  array<string, mixed>  $rules
     * @return array{points: int, speed_bonus: int, streak_bonus: int, is_correct: bool, streak: int}
     */
    public function scoreAnswer(
        QuizSession $session,
        QuizPlayer $player,
        QuizQuestion $question,
        ?int $selectedIndex,
        ?string $selectedText,
        ?int $responseMs,
        bool $timedOut,
    ): array {
        $rules = $session->scoring_rules ?? QuizConfig::DEFAULT_SCORING;
        $correctPoints = (int) ($rules['correct_points'] ?? 100);
        $speedBonusMax = (int) ($rules['speed_bonus_max'] ?? 50);
        $streakBonusPer = (int) ($rules['streak_bonus_per'] ?? 10);

        $isCorrect = false;
        if (! $timedOut) {
            $isCorrect = $this->isCorrect($question, $selectedIndex, $selectedText);
        }

        if (! $isCorrect) {
            return [
                'points' => 0,
                'speed_bonus' => 0,
                'streak_bonus' => 0,
                'is_correct' => false,
                'streak' => 0,
            ];
        }

        $speedBonus = 0;
        $timeLimitMs = max(1, (int) $session->time_per_question) * 1000;
        if ($responseMs !== null && $responseMs >= 0 && $responseMs < $timeLimitMs && $speedBonusMax > 0) {
            $ratio = 1 - ($responseMs / $timeLimitMs);
            $speedBonus = (int) max(0, round($speedBonusMax * $ratio));
        }

        $newStreak = (int) $player->streak + 1;
        $streakBonus = $newStreak > 1 ? ($newStreak - 1) * $streakBonusPer : 0;

        // Speed quiz mode amplifies speed bonus.
        if ($session->mode === QuizSession::MODE_SPEED) {
            $speedBonus = (int) round($speedBonus * 1.5);
        }

        return [
            'points' => $correctPoints + $speedBonus + $streakBonus,
            'speed_bonus' => $speedBonus,
            'streak_bonus' => $streakBonus,
            'is_correct' => true,
            'streak' => $newStreak,
        ];
    }

    public function isCorrect(QuizQuestion $question, ?int $selectedIndex, ?string $selectedText): bool
    {
        $type = $question->type ?? 'multiple_choice';

        if (in_array($type, ['multiple_choice', 'true_false', 'image', 'audio', 'video', 'emoji', 'puzzle'], true)) {
            if ($selectedIndex === null) {
                return false;
            }

            return (int) $selectedIndex === (int) $question->correct_index;
        }

        if ($type === 'text') {
            $options = $question->options ?? [];
            $expected = isset($options[(int) $question->correct_index])
                ? (string) $options[(int) $question->correct_index]
                : '';
            $answer = trim((string) ($selectedText ?? ''));

            return $expected !== '' && strcasecmp($answer, trim($expected)) === 0;
        }

        if ($selectedIndex === null) {
            return false;
        }

        return (int) $selectedIndex === (int) $question->correct_index;
    }
}

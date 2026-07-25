<?php

namespace App\Features\Games\Plugins\Quiz\Support;

use App\Features\Games\Plugins\Quiz\Models\QuizAnswer;
use App\Features\Games\Plugins\Quiz\Models\QuizPlayer;
use App\Features\Games\Plugins\Quiz\Models\QuizReward;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Games\Plugins\Quiz\Models\QuizTournament;
use Illuminate\Broadcasting\PrivateChannel;

final class QuizBroadcast
{
    /** @return list<PrivateChannel> */
    public static function channelsFor(QuizSession $session, ?string $extraUserId = null): array
    {
        $channels = [
            new PrivateChannel('quiz.session.'.$session->getKey()),
        ];

        foreach (array_unique(array_filter([(string) $session->host_id, $extraUserId])) as $userId) {
            $channels[] = new PrivateChannel('user.games.'.$userId);
            $channels[] = new PrivateChannel('user.quiz.'.$userId);
        }

        if ($session->relationLoaded('players')) {
            foreach ($session->players as $player) {
                $uid = (string) $player->user_id;
                $channels[] = new PrivateChannel('user.games.'.$uid);
                $channels[] = new PrivateChannel('user.quiz.'.$uid);
            }
        }

        if ($session->room_id !== null && $session->room_id !== '') {
            $channels[] = new PrivateChannel(sprintf(
                'room.quiz.%s.%s',
                $session->room_type ?? 'unknown',
                $session->room_id,
            ));
        }

        return array_values(array_unique($channels, SORT_REGULAR));
    }

    /** @return array<string, mixed> */
    public static function sessionPayload(QuizSession $session): array
    {
        return [
            'event_id' => $session->id,
            'session_id' => $session->id,
            'host_id' => $session->host_id,
            'game_id' => $session->game_id,
            'tournament_id' => $session->tournament_id,
            'category_id' => $session->category_id,
            'mode' => $session->mode,
            'difficulty' => $session->difficulty,
            'status' => $session->status,
            'visibility' => $session->visibility,
            'eligibility' => $session->eligibility,
            'reward_type' => $session->reward_type,
            'reward_pool' => (int) $session->reward_pool,
            'bet_coins' => (int) $session->bet_coins,
            'question_count' => (int) $session->question_count,
            'time_per_question' => (int) $session->time_per_question,
            'winner_count' => (int) $session->winner_count,
            'current_round' => (int) $session->current_round,
            'current_question_index' => (int) $session->current_question_index,
            'current_question_id' => $session->current_question_id,
            'player_count' => (int) $session->player_count,
            'room_id' => $session->room_id,
            'room_type' => $session->room_type,
            'language' => $session->language,
            'question_started_at' => $session->question_started_at?->toISOString(),
            'question_ends_at' => $session->question_ends_at?->toISOString(),
            'started_at' => $session->started_at?->toISOString(),
            'finished_at' => $session->finished_at?->toISOString(),
            'created_at' => $session->created_at?->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $question
     * @return array<string, mixed>
     */
    public static function questionPayload(QuizSession $session, ?array $question = null, bool $reveal = false): array
    {
        $payload = self::sessionPayload($session);
        if ($question !== null) {
            $public = $question;
            if (! $reveal) {
                unset($public['correct_index'], $public['is_correct']);
                if (isset($public['options']) && is_array($public['options'])) {
                    $public['options'] = array_map(static function ($opt) {
                        if (is_array($opt)) {
                            unset($opt['is_correct']);
                        }

                        return $opt;
                    }, $public['options']);
                }
            }
            $payload['question'] = $public;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    public static function answerPayload(QuizSession $session, QuizAnswer $answer): array
    {
        return array_merge(self::sessionPayload($session), [
            'event_id' => $answer->id,
            'answer_id' => $answer->id,
            'player_id' => $answer->player_id,
            'user_id' => $answer->user_id,
            'question_id' => $answer->question_id,
            'is_correct' => (bool) $answer->is_correct,
            'points_awarded' => (int) $answer->points_awarded,
            'speed_bonus' => (int) $answer->speed_bonus,
            'streak_bonus' => (int) $answer->streak_bonus,
            'response_ms' => $answer->response_ms,
            'timed_out' => (bool) $answer->timed_out,
            'answered_at' => $answer->answered_at?->toISOString(),
        ]);
    }

    /** @return array<string, mixed> */
    public static function scorePayload(QuizSession $session, QuizPlayer $player): array
    {
        return array_merge(self::sessionPayload($session), [
            'event_id' => $player->id,
            'player_id' => $player->id,
            'user_id' => $player->user_id,
            'score' => (int) $player->score,
            'correct_count' => (int) $player->correct_count,
            'wrong_count' => (int) $player->wrong_count,
            'streak' => (int) $player->streak,
            'best_streak' => (int) $player->best_streak,
            'rank' => $player->rank,
            'is_winner' => (bool) $player->is_winner,
            'team_id' => $player->team_id,
        ]);
    }

    /** @return array<string, mixed> */
    public static function rewardPayload(QuizSession $session, QuizReward $reward): array
    {
        return array_merge(self::sessionPayload($session), [
            'event_id' => $reward->id,
            'reward_id' => $reward->id,
            'user_id' => $reward->user_id,
            'reward_type' => $reward->reward_type,
            'amount' => (int) $reward->amount,
            'currency' => $reward->currency,
            'rank' => $reward->rank,
            'status' => $reward->status,
            'is_jackpot' => (bool) $reward->is_jackpot,
            'is_mystery' => (bool) $reward->is_mystery,
            'is_vip' => (bool) $reward->is_vip,
        ]);
    }

    /** @return array<string, mixed> */
    public static function tournamentPayload(QuizTournament $tournament): array
    {
        return [
            'event_id' => $tournament->id,
            'tournament_id' => $tournament->id,
            'slug' => $tournament->slug,
            'name' => $tournament->name,
            'status' => $tournament->status,
            'rounds' => (int) $tournament->rounds,
            'current_round' => (int) $tournament->current_round,
            'max_players' => (int) $tournament->max_players,
            'entry_fee_coins' => (int) $tournament->entry_fee_coins,
            'reward_type' => $tournament->reward_type,
            'reward_pool' => (int) $tournament->reward_pool,
            'scheduled_at' => $tournament->scheduled_at?->toISOString(),
            'started_at' => $tournament->started_at?->toISOString(),
            'finished_at' => $tournament->finished_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Features\Games\Plugins\Quiz\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\Quiz\Events\AnswerSubmitted;
use App\Features\Games\Plugins\Quiz\Events\QuizFinished;
use App\Features\Games\Plugins\Quiz\Events\QuizJoined;
use App\Features\Games\Plugins\Quiz\Events\QuizStarted;
use App\Features\Games\Plugins\Quiz\Events\RewardGranted;
use App\Features\Games\Plugins\Quiz\Events\TournamentFinished;
use App\Features\Games\Repositories\Contracts\GameRepository;
use App\Features\Users\Models\User;

final class RecordQuizAnalytics
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(
        QuizStarted|QuizJoined|AnswerSubmitted|QuizFinished|RewardGranted|TournamentFinished $event
    ): void {
        if ($event instanceof TournamentFinished) {
            $game = Game::query()->where('slug', Game::SLUG_QUIZ)->first();
            $this->games->trackAnalytics(null, $game, 'tournament_completed', [
                'tournament_id' => $event->tournament->id,
                'status' => $event->tournament->status,
                'reward_pool' => (int) $event->tournament->reward_pool,
            ]);

            return;
        }

        $session = $event->session;
        $game = $session->game_id !== null
            ? Game::query()->find($session->game_id)
            : Game::query()->where('slug', Game::SLUG_QUIZ)->first();

        $actor = match (true) {
            $event instanceof QuizJoined => $event->player->user ?? User::query()->find($event->player->user_id),
            $event instanceof AnswerSubmitted => User::query()->find($event->answer->user_id),
            $event instanceof RewardGranted => User::query()->find($event->reward->user_id),
            default => $session->host ?? User::query()->find($session->host_id),
        };

        $eventName = match (true) {
            $event instanceof QuizJoined => 'quiz_joined',
            $event instanceof AnswerSubmitted => 'question_answered',
            $event instanceof QuizFinished => 'quiz_finished',
            $event instanceof RewardGranted => 'reward_granted',
            default => 'quiz_started',
        };

        $payload = [
            'session_id' => $session->id,
            'host_id' => $session->host_id,
            'mode' => $session->mode,
            'difficulty' => $session->difficulty,
            'status' => $session->status,
            'reward_type' => $session->reward_type,
            'reward_pool' => (int) $session->reward_pool,
            'player_count' => (int) $session->player_count,
            'room_id' => $session->room_id,
            'room_type' => $session->room_type,
        ];

        if ($event instanceof QuizJoined) {
            $payload['player_id'] = $event->player->id;
            $payload['joiner_id'] = $event->player->user_id;
        }

        if ($event instanceof AnswerSubmitted) {
            $payload['answer_id'] = $event->answer->id;
            $payload['question_id'] = $event->answer->question_id;
            $payload['is_correct'] = (bool) $event->answer->is_correct;
            $payload['points_awarded'] = (int) $event->answer->points_awarded;
        }

        if ($event instanceof RewardGranted) {
            $payload['reward_id'] = $event->reward->id;
            $payload['amount'] = (int) $event->reward->amount;
            $payload['rank'] = $event->reward->rank;
        }

        $this->games->trackAnalytics($actor, $game, $eventName, $payload);
    }
}

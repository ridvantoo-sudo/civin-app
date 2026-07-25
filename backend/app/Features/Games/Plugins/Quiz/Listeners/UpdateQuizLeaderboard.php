<?php

namespace App\Features\Games\Plugins\Quiz\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\Quiz\Events\QuizFinished;
use App\Features\Games\Plugins\Quiz\Events\RewardGranted;
use App\Features\Games\Plugins\Quiz\Models\QuizPlayer;
use App\Features\Games\Repositories\Contracts\GameRepository;
use App\Features\Users\Models\User;

/**
 * Lightweight leaderboard bump via GameRepository. Period boards are also
 * computed from quiz_histories in QuizRepository::leaderboard().
 */
final class UpdateQuizLeaderboard
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(QuizFinished|RewardGranted $event): void
    {
        $session = $event->session;
        $game = $session->game_id !== null
            ? Game::query()->find($session->game_id)
            : Game::query()->where('slug', Game::SLUG_QUIZ)->first();

        if ($game === null) {
            return;
        }

        if ($event instanceof RewardGranted) {
            $user = User::query()->find($event->reward->user_id);
            if ($user === null) {
                return;
            }
            $reward = $event->reward->currency === 'diamonds' ? 0 : (int) $event->reward->amount;
            $this->games->bumpLeaderboard($user, $game, 0, $reward, true);

            return;
        }

        $players = QuizPlayer::query()->where('session_id', $session->getKey())->get();
        foreach ($players as $player) {
            $user = User::query()->find($player->user_id);
            if ($user === null) {
                continue;
            }
            $this->games->bumpLeaderboard(
                $user,
                $game,
                (int) $session->bet_coins,
                0,
                (bool) $player->is_winner,
            );
        }
    }
}

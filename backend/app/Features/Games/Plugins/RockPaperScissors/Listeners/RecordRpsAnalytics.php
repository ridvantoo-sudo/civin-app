<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsChallengeAccepted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsChallengeCreated;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMatchFinished;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMatchStarted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMoveSubmitted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsRewardGranted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsRoundFinished;
use App\Features\Games\Plugins\RockPaperScissors\Events\TournamentFinished;
use App\Features\Games\Plugins\RockPaperScissors\Events\TournamentStarted;
use App\Features\Games\Repositories\Contracts\GameRepository;
use App\Features\Users\Models\User;

final class RecordRpsAnalytics
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(
        RpsChallengeCreated|RpsChallengeAccepted|RpsMatchStarted|RpsMoveSubmitted|RpsRoundFinished|RpsMatchFinished|RpsRewardGranted|TournamentStarted|TournamentFinished $event
    ): void {
        if ($event instanceof TournamentStarted || $event instanceof TournamentFinished) {
            $game = Game::query()->where('slug', Game::SLUG_ROCK_PAPER_SCISSORS)->first();
            $this->games->trackAnalytics(null, $game, $event instanceof TournamentStarted
                ? 'rps_tournament_started'
                : 'rps_tournament_finished', [
                    'tournament_id' => (string) $event->tournament->getKey(),
                    'format' => $event->tournament->format,
                    'status' => $event->tournament->status,
                    'registered_count' => (int) $event->tournament->registered_count,
                    'reward_pool' => (int) $event->tournament->reward_pool,
                ]);

            return;
        }

        $match = $event->match;
        $game = $match->game_id !== null
            ? Game::query()->find($match->game_id)
            : Game::query()->where('slug', Game::SLUG_ROCK_PAPER_SCISSORS)->first();

        $actor = match (true) {
            $event instanceof RpsMoveSubmitted => User::query()->find($event->move->user_id),
            $event instanceof RpsRewardGranted => User::query()->find($event->reward->user_id),
            $event instanceof RpsChallengeAccepted => $match->opponent_id === null
                ? null
                : User::query()->find($match->opponent_id),
            default => $match->challenger ?? User::query()->find($match->challenger_id),
        };

        $eventName = match (true) {
            $event instanceof RpsChallengeCreated => 'rps_challenge_created',
            $event instanceof RpsChallengeAccepted => 'rps_challenge_accepted',
            $event instanceof RpsMatchStarted => 'rps_match_started',
            $event instanceof RpsMoveSubmitted => 'rps_move_submitted',
            $event instanceof RpsRoundFinished => 'rps_round_finished',
            $event instanceof RpsRewardGranted => 'rps_reward_granted',
            default => 'rps_match_finished',
        };

        $payload = [
            'match_id' => (string) $match->getKey(),
            'challenger_id' => $match->challenger_id,
            'opponent_id' => $match->opponent_id,
            'mode' => $match->mode,
            'matchmaking' => $match->matchmaking,
            'status' => $match->status,
            'result' => $match->result,
            'entry_fee_coins' => (int) $match->entry_fee_coins,
            'pot_coins' => (int) $match->pot_coins,
            'rounds_played' => (int) $match->rounds_played,
            'room_id' => $match->room_id,
            'room_type' => $match->room_type,
            'tournament_id' => $match->tournament_id,
        ];

        if ($event instanceof RpsMoveSubmitted) {
            // Deliberately excludes the move value; analytics never sees it.
            $payload['move_id'] = (string) $event->move->getKey();
            $payload['round_index'] = (int) $event->move->round_index;
            $payload['timed_out'] = (bool) $event->move->timed_out;
        }

        if ($event instanceof RpsRoundFinished) {
            $payload['round_id'] = (string) $event->round->getKey();
            $payload['round_result'] = $event->round->result;
        }

        if ($event instanceof RpsRewardGranted) {
            $payload['reward_id'] = (string) $event->reward->getKey();
            $payload['amount'] = (int) $event->reward->amount;
            $payload['reward_status'] = $event->reward->status;
        }

        $this->games->trackAnalytics($actor, $game, $eventName, $payload);
    }
}

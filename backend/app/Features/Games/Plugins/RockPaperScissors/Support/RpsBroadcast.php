<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Support;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMove;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsReward;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsRound;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsTournament;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Broadcast channels and payloads.
 *
 * Move values are only ever included once the owning round is resolved and
 * flagged as revealed; before that clients receive submission flags only.
 */
final class RpsBroadcast
{
    /** @return list<PrivateChannel> */
    public static function channelsFor(RpsMatch $match, ?string $extraUserId = null): array
    {
        $channels = [
            new PrivateChannel('rps.match.'.$match->getKey()),
        ];

        $userIds = array_unique(array_filter(array_merge($match->participantIds(), [$extraUserId])));
        foreach ($userIds as $userId) {
            $channels[] = new PrivateChannel('user.games.'.$userId);
            $channels[] = new PrivateChannel('user.rps.'.$userId);
        }

        if ($match->room_id !== null && $match->room_id !== '') {
            $channels[] = new PrivateChannel(sprintf(
                'room.rps.%s.%s',
                $match->room_type ?? 'unknown',
                $match->room_id,
            ));
        }

        if ($match->tournament_id !== null) {
            $channels[] = new PrivateChannel('rps.tournament.'.$match->tournament_id);
        }

        return array_values(array_unique($channels, SORT_REGULAR));
    }

    /** @return list<PrivateChannel> */
    public static function tournamentChannels(RpsTournament $tournament): array
    {
        return [
            new PrivateChannel('rps.tournament.'.$tournament->getKey()),
            new PrivateChannel('rps.tournaments'),
        ];
    }

    /** @return array<string, mixed> */
    public static function matchPayload(RpsMatch $match): array
    {
        return [
            'event_id' => $match->id,
            'match_id' => $match->id,
            'config_id' => $match->config_id,
            'game_id' => $match->game_id,
            'tournament_id' => $match->tournament_id,
            'bracket_id' => $match->bracket_id,
            'parent_match_id' => $match->parent_match_id,
            'challenger_id' => $match->challenger_id,
            'opponent_id' => $match->opponent_id,
            'winner_id' => $match->winner_id,
            'mode' => $match->mode,
            'matchmaking' => $match->matchmaking,
            'status' => $match->status,
            'result' => $match->result,
            'visibility' => $match->visibility,
            'eligibility' => $match->eligibility,
            'reward_type' => $match->reward_type,
            'entry_fee_coins' => (int) $match->entry_fee_coins,
            'pot_coins' => (int) $match->pot_coins,
            'rounds_target' => (int) $match->rounds_target,
            'wins_required' => (int) $match->wins_required,
            'rounds_played' => (int) $match->rounds_played,
            'challenger_score' => (int) $match->challenger_score,
            'opponent_score' => (int) $match->opponent_score,
            'draw_count' => (int) $match->draw_count,
            'current_round' => (int) $match->current_round,
            'round_timeout_seconds' => (int) $match->round_timeout_seconds,
            'room_id' => $match->room_id,
            'room_type' => $match->room_type,
            'is_vip_arena' => (bool) $match->is_vip_arena,
            'is_rematch' => (bool) $match->is_rematch,
            'moves_available' => $match->moveRules()['moves'] ?? [],
            'accept_deadline_at' => $match->accept_deadline_at?->toISOString(),
            'started_at' => $match->started_at?->toISOString(),
            'finished_at' => $match->finished_at?->toISOString(),
            'created_at' => $match->created_at?->toISOString(),
        ];
    }

    /**
     * Round state. Move values appear only when `$round->revealed` is true.
     *
     * @return array<string, mixed>
     */
    public static function roundPayload(RpsMatch $match, RpsRound $round): array
    {
        $payload = array_merge(self::matchPayload($match), [
            'event_id' => $round->id,
            'round_id' => $round->id,
            'round_index' => (int) $round->round_index,
            'round_status' => $round->status,
            'challenger_submitted' => (bool) $round->challenger_submitted,
            'opponent_submitted' => (bool) $round->opponent_submitted,
            'revealed' => (bool) $round->revealed,
            'round_started_at' => $round->started_at?->toISOString(),
            'round_ends_at' => $round->ends_at?->toISOString(),
        ]);

        if ($round->revealed) {
            $payload['round_result'] = $round->result;
            $payload['round_winner_id'] = $round->winner_id;
            $payload['challenger_move'] = $round->challenger_move;
            $payload['opponent_move'] = $round->opponent_move;
            $payload['resolved_at'] = $round->resolved_at?->toISOString();
        }

        return $payload;
    }

    /**
     * Submission acknowledgement. Deliberately omits the move itself.
     *
     * @return array<string, mixed>
     */
    public static function movePayload(RpsMatch $match, RpsRound $round, RpsMove $move): array
    {
        return array_merge(self::roundPayload($match, $round), [
            'event_id' => $move->id,
            'move_id' => $move->id,
            'user_id' => $move->user_id,
            'role' => $move->role,
            'is_auto' => (bool) $move->is_auto,
            'timed_out' => (bool) $move->timed_out,
            'submitted_at' => $move->submitted_at?->toISOString(),
        ]);
    }

    /** @return array<string, mixed> */
    public static function rewardPayload(RpsMatch $match, RpsReward $reward): array
    {
        return array_merge(self::matchPayload($match), [
            'event_id' => $reward->id,
            'reward_id' => $reward->id,
            'user_id' => $reward->user_id,
            'reward_type' => $reward->reward_type,
            'amount' => (int) $reward->amount,
            'currency' => $reward->currency,
            'rank' => $reward->rank,
            'status' => $reward->status,
            'is_vip' => (bool) $reward->is_vip,
            'is_tournament' => (bool) $reward->is_tournament,
            'is_refund' => (bool) $reward->is_refund,
        ]);
    }

    /** @return array<string, mixed> */
    public static function tournamentPayload(RpsTournament $tournament): array
    {
        return [
            'event_id' => $tournament->id,
            'tournament_id' => $tournament->id,
            'slug' => $tournament->slug,
            'name' => $tournament->name,
            'format' => $tournament->format,
            'status' => $tournament->status,
            'mode' => $tournament->mode,
            'rounds' => (int) $tournament->rounds,
            'current_round' => (int) $tournament->current_round,
            'max_players' => (int) $tournament->max_players,
            'registered_count' => (int) $tournament->registered_count,
            'entry_fee_coins' => (int) $tournament->entry_fee_coins,
            'reward_type' => $tournament->reward_type,
            'reward_pool' => (int) $tournament->reward_pool,
            'is_vip' => (bool) $tournament->is_vip,
            'is_daily' => (bool) $tournament->is_daily,
            'winner_id' => $tournament->winner_id,
            'scheduled_at' => $tournament->scheduled_at?->toISOString(),
            'started_at' => $tournament->started_at?->toISOString(),
            'finished_at' => $tournament->finished_at?->toISOString(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    public static function leaderboardPayload(string $period, array $entries): array
    {
        return [
            'event_id' => $period.':'.now()->timestamp,
            'period' => $period,
            'entries' => $entries,
            'generated_at' => now()->toISOString(),
        ];
    }
}

<?php

namespace App\Features\Games\Plugins\RedEnvelope\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameLeaderboardEntry;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeClaimed;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeCreated;
use App\Features\Games\Repositories\Contracts\GameRepository;
use App\Features\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Senders are ranked by what they funded (coins_wagered); receivers by what
 * they claimed (coins_net). Both feed the shared `score` column.
 */
final class UpdateRedEnvelopeLeaderboard
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(EnvelopeCreated|EnvelopeClaimed $event): void
    {
        $envelope = $event->envelope;
        $isClaim = $event instanceof EnvelopeClaimed;

        $user = $isClaim
            ? $event->claim->user
            : ($envelope->creator ?? $envelope->sender);

        if (! $user instanceof User) {
            return;
        }

        $game = $envelope->game_id !== null
            ? Game::query()->find($envelope->game_id)
            : Game::query()->where('slug', Game::SLUG_RED_ENVELOPE)->first();

        if ($game === null) {
            return;
        }

        // Diamond envelopes do not contribute to the coin-denominated columns.
        $paysCoins = $envelope->paysCoins();
        $wagered = $isClaim || ! $paysCoins ? 0 : (int) ($envelope->total_amount ?? 0);
        $reward = $isClaim && $paysCoins ? $event->claim->rewardAmount() : 0;

        $this->games->bumpLeaderboard($user, $game, $wagered, $reward, $isClaim);
        $this->bumpMonthly((string) $user->getKey(), (string) $game->getKey(), $wagered, $reward, $isClaim);
    }

    private function bumpMonthly(string $userId, string $gameId, int $wagered, int $reward, bool $won): void
    {
        $periodKey = now()->format('Y-m');
        $net = $reward - $wagered;
        $scoreDelta = max(0, $reward) + ($won ? 25 : 0);

        DB::transaction(function () use ($userId, $gameId, $periodKey, $wagered, $net, $won, $scoreDelta): void {
            $entry = GameLeaderboardEntry::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'game_id' => $gameId,
                    'period' => 'monthly',
                    'period_key' => $periodKey,
                ],
                [
                    'plays' => 0,
                    'wins' => 0,
                    'coins_wagered' => 0,
                    'coins_net' => 0,
                    'score' => 0,
                ],
            );

            $locked = GameLeaderboardEntry::query()->whereKey($entry->getKey())->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }

            $locked->forceFill([
                'plays' => $locked->plays + 1,
                'wins' => $locked->wins + ($won ? 1 : 0),
                'coins_wagered' => $locked->coins_wagered + $wagered,
                'coins_net' => $locked->coins_net + $net,
                'score' => $locked->score + $scoreDelta,
            ])->save();
        });
    }
}

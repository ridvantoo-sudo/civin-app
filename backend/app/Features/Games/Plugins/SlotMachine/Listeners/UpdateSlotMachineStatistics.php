<?php

namespace App\Features\Games\Plugins\SlotMachine\Listeners;

use App\Features\Games\Plugins\SlotMachine\Events\SlotSpinFinished;
use App\Features\Games\Plugins\SlotMachine\Repositories\Contracts\SlotMachineRepository;

final class UpdateSlotMachineStatistics
{
    public function __construct(private readonly SlotMachineRepository $slots) {}

    public function handle(SlotSpinFinished $event): void
    {
        $spin = $event->spin;
        $existing = $this->slots->statistics($spin->user, $spin->config_id);

        $gamesPlayed = (int) ($existing?->games_played ?? 0) + 1;
        $wagered = (int) ($existing?->coins_wagered ?? 0) + (int) $spin->cost_coins;
        $won = (int) ($existing?->coins_won ?? 0) + (int) $spin->reward_coins;
        $diamonds = (int) ($existing?->diamonds_won ?? 0) + (int) $spin->reward_diamonds;
        $jackpots = (int) ($existing?->jackpots ?? 0) + ($spin->is_jackpot ? 1 : 0);
        $bonus = (int) ($existing?->bonus_wins ?? 0) + ($spin->is_bonus ? 1 : 0);
        $free = (int) ($existing?->free_spins_played ?? 0) + ($spin->is_free_spin ? 1 : 0);
        $highest = max((int) ($existing?->highest_win ?? 0), (int) $spin->reward_coins);
        $average = $gamesPlayed > 0 ? round($won / $gamesPlayed, 2) : 0;
        $rtp = $wagered > 0 ? round(($won / $wagered) * 100, 4) : 0;

        $this->slots->upsertStatistics($spin->user, $spin->config_id, [
            'games_played' => $gamesPlayed,
            'coins_wagered' => $wagered,
            'coins_won' => $won,
            'diamonds_won' => $diamonds,
            'jackpots' => $jackpots,
            'bonus_wins' => $bonus,
            'free_spins_played' => $free,
            'highest_win' => $highest,
            'average_win' => $average,
            'rtp_percent' => $rtp,
        ]);
    }
}

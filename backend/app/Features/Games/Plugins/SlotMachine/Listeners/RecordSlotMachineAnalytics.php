<?php

namespace App\Features\Games\Plugins\SlotMachine\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\SlotMachine\Events\BonusTriggered;
use App\Features\Games\Plugins\SlotMachine\Events\JackpotWon;
use App\Features\Games\Plugins\SlotMachine\Events\SlotSpinFinished;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSpin;
use App\Features\Games\Repositories\Contracts\GameRepository;

final class RecordSlotMachineAnalytics
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(object $event): void
    {
        if (! property_exists($event, 'spin') || ! $event->spin instanceof SlotMachineSpin) {
            return;
        }

        $spin = $event->spin;
        $game = $spin->game_id !== null
            ? Game::query()->find($spin->game_id)
            : Game::query()->where('slug', Game::SLUG_SLOT_MACHINE)->first();

        $eventName = match (true) {
            $event instanceof JackpotWon => 'JackpotWon',
            $event instanceof BonusTriggered => 'BonusTriggered',
            $spin->is_free_spin && $event instanceof SlotSpinFinished => 'FreeSpinFinished',
            $spin->is_win => 'SlotWon',
            default => 'SlotLost',
        };

        if ($event instanceof SlotSpinFinished) {
            $this->games->trackAnalytics($spin->user, $game, 'SlotPlayed', [
                'spin_id' => $spin->id,
                'idempotency_key' => $spin->idempotency_key,
                'game_mode' => $spin->game_mode,
                'cost_coins' => $spin->cost_coins,
                'reward_coins' => $spin->reward_coins,
                'is_jackpot' => $spin->is_jackpot,
                'is_bonus' => $spin->is_bonus,
                'is_free_spin' => $spin->is_free_spin,
            ]);
            if ($spin->is_free_spin) {
                $this->games->trackAnalytics($spin->user, $game, 'FreeSpinStarted', [
                    'spin_id' => $spin->id,
                ]);
            }
        }

        $this->games->trackAnalytics($spin->user, $game, $eventName, [
            'spin_id' => $spin->id,
            'idempotency_key' => $spin->idempotency_key,
            'game_mode' => $spin->game_mode,
            'cost_coins' => $spin->cost_coins,
            'reward_coins' => $spin->reward_coins,
            'is_jackpot' => $spin->is_jackpot,
            'is_bonus' => $spin->is_bonus,
        ]);
    }
}

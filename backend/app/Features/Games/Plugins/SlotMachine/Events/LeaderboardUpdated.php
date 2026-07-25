<?php

namespace App\Features\Games\Plugins\SlotMachine\Events;

use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSpin;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LeaderboardUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly SlotMachineSpin $spin) {}
}

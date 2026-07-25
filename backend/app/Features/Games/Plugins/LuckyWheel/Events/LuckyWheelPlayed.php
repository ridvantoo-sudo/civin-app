<?php

namespace App\Features\Games\Plugins\LuckyWheel\Events;

use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSpin;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LuckyWheelPlayed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly LuckyWheelSpin $spin) {}
}

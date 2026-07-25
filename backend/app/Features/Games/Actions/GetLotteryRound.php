<?php

namespace App\Features\Games\Actions;

use App\Features\Games\Models\LotteryRound;
use App\Features\Games\Services\GameService;

final readonly class GetLotteryRound
{
    public function __construct(private GameService $games) {}

    public function execute(): LotteryRound
    {
        return $this->games->currentLotteryRound();
    }
}

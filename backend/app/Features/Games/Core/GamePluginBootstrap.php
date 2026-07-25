<?php

namespace App\Features\Games\Core;

use App\Features\Games\Plugins\Lottery\LotteryPlugin;
use App\Features\Games\Plugins\LuckyDice\LuckyDicePlugin;
use App\Features\Games\Plugins\LuckyNumber\LuckyNumberPlugin;
use App\Features\Games\Plugins\LuckyWheel\LuckyWheelPlugin;
use App\Features\Games\Plugins\Ludo\LudoPlugin;
use App\Features\Games\Plugins\Quiz\QuizPlugin;
use App\Features\Games\Plugins\RedEnvelope\RedEnvelopePlugin;
use App\Features\Games\Plugins\RockPaperScissors\RockPaperScissorsPlugin;
use App\Features\Games\Plugins\SlotMachine\SlotMachinePlugin;
use App\Features\Games\Plugins\TreasureBox\TreasureBoxPlugin;

final class GamePluginBootstrap
{
    public function __construct(
        private readonly LuckyWheelPlugin $luckyWheel,
        private readonly LuckyDicePlugin $luckyDice,
        private readonly LuckyNumberPlugin $luckyNumber,
        private readonly TreasureBoxPlugin $treasureBox,
        private readonly RedEnvelopePlugin $redEnvelope,
        private readonly QuizPlugin $quiz,
        private readonly LotteryPlugin $lottery,
        private readonly SlotMachinePlugin $slotMachine,
        private readonly RockPaperScissorsPlugin $rps,
        private readonly LudoPlugin $ludo,
    ) {}

    public function register(GameRegistry $registry): void
    {
        $registry->registerAll([
            $this->luckyWheel,
            $this->luckyDice,
            $this->luckyNumber,
            $this->treasureBox,
            $this->redEnvelope,
            $this->quiz,
            $this->lottery,
            $this->slotMachine,
            $this->rps,
            $this->ludo,
        ]);
    }
}

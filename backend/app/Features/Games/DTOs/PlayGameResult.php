<?php

namespace App\Features\Games\DTOs;

use App\Features\Games\Models\GamePlay;
use App\Features\Wallet\Models\Wallet;

final readonly class PlayGameResult
{
    /**
     * @param  array<string, mixed>  $outcome
     */
    public function __construct(
        public GamePlay $play,
        public array $outcome,
        public Wallet $wallet,
    ) {}
}

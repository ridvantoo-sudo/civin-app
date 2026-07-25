<?php

namespace App\Features\Games\Plugins\Ludo\Services;

use App\Features\Games\Plugins\Ludo\Support\LudoRuleEngine;

final readonly class LudoBoardService
{
    public function __construct(private LudoRuleEngine $rules) {}

    public function legalMoves(array $board, string $color, int $dice): array
    {
        return $this->rules->legalMoves($board, $color, $dice);
    }

    public function move(array $board, string $color, int $piece, int $dice): array
    {
        return $this->rules->applyMove($board, $color, $piece, $dice);
    }
}

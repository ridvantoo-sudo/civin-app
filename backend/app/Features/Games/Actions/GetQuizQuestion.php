<?php

namespace App\Features\Games\Actions;

use App\Features\Games\Services\GameService;

final readonly class GetQuizQuestion
{
    public function __construct(private GameService $games) {}

    /** @return array<string, mixed> */
    public function execute(): array
    {
        return $this->games->quizQuestion();
    }
}

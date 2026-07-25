<?php

namespace App\Features\Games\Plugins\Quiz\DTOs;

final readonly class QuizDTO
{
    /**
     * @param  list<string>|null  $questionIds
     * @param  list<string>|null  $whitelistUserIds
     * @param  array<string, mixed>  $teamSettings
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $idempotencyKey,
        public string $mode = 'classic',
        public ?string $categoryId = null,
        public string $difficulty = 'medium',
        public ?array $questionIds = null,
        public int $questionCount = 10,
        public int $timePerQuestion = 15,
        public string $rewardType = 'coins',
        public int $rewardPool = 0,
        public int $betCoins = 0,
        public int $winnerCount = 3,
        public string $visibility = 'public',
        public string $eligibility = 'everyone',
        public ?array $whitelistUserIds = null,
        public ?string $roomId = null,
        public ?string $roomType = null,
        public ?string $tournamentId = null,
        public string $language = 'en',
        public array $teamSettings = [],
        public ?string $clientSessionId = null,
        public array $metadata = [],
    ) {}
}

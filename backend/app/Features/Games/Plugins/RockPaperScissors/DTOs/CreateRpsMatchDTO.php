<?php

namespace App\Features\Games\Plugins\RockPaperScissors\DTOs;

final readonly class CreateRpsMatchDTO
{
    /**
     * @param  list<string>|null  $whitelistUserIds
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $idempotencyKey,
        public string $mode = 'classic_1v1',
        public string $matchmaking = 'private',
        public ?string $opponentId = null,
        public int $entryFeeCoins = 0,
        public string $rewardType = 'coins',
        public string $visibility = 'private',
        public string $eligibility = 'everyone',
        public ?array $whitelistUserIds = null,
        public ?int $roundTimeoutSeconds = null,
        public ?string $roomId = null,
        public ?string $roomType = null,
        public ?string $tournamentId = null,
        public ?string $clientMatchId = null,
        public array $metadata = [],
    ) {}
}

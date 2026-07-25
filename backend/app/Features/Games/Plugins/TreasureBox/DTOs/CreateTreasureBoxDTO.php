<?php

namespace App\Features\Games\Plugins\TreasureBox\DTOs;

final readonly class CreateTreasureBoxDTO
{
    /**
     * @param  list<string>|null  $whitelistUserIds
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $idempotencyKey,
        public string $type,
        public string $rewardType,
        public string $distributionType,
        public string $visibility,
        public string $eligibilityScope,
        public int $totalAmount,
        public int $winnerCount,
        public int $countdownSeconds,
        public int $claimDurationSeconds,
        public bool $autoStart = true,
        public ?string $scheduledAt = null,
        public ?string $roomType = null,
        public ?string $roomId = null,
        public ?array $whitelistUserIds = null,
        public ?int $minWatchTimeSeconds = null,
        public ?int $minUserLevel = null,
        public ?int $minGiftValue = null,
        public ?string $message = null,
        public array $metadata = [],
    ) {}
}

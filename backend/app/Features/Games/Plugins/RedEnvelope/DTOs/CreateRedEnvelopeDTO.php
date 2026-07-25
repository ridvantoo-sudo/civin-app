<?php

namespace App\Features\Games\Plugins\RedEnvelope\DTOs;

final readonly class CreateRedEnvelopeDTO
{
    /**
     * @param  list<string>|null  $whitelistUserIds
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $idempotencyKey,
        public string $rewardType,
        public string $distributionType,
        public string $visibility,
        public string $claimScope,
        public int $totalAmount,
        public int $winnerCount,
        public int $durationSeconds,
        public ?string $roomType = null,
        public ?string $roomId = null,
        public ?array $whitelistUserIds = null,
        public ?string $message = null,
        public array $metadata = [],
    ) {}
}

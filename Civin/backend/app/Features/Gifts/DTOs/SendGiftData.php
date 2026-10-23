<?php

namespace App\Features\Gifts\DTOs;

final readonly class SendGiftData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $giftId,
        public int $quantity = 1,
        public ?array $metadata = null,
        public ?string $clientRequestId = null,
    ) {}
}

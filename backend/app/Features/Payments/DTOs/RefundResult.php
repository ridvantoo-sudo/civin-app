<?php

namespace App\Features\Payments\DTOs;

final readonly class RefundResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $succeeded,
        public int $amountMinor,
        public ?string $providerReference = null,
        public array $raw = [],
    ) {}
}

<?php

namespace App\Features\Games\Plugins\RedEnvelope\DTOs;

final readonly class ClaimRedEnvelopeDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $idempotencyKey,
        public string $envelopeId,
        public array $metadata = [],
    ) {}
}

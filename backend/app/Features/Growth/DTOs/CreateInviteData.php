<?php

namespace App\Features\Growth\DTOs;

final readonly class CreateInviteData
{
    public function __construct(
        public string $channel = 'link',
        public ?string $target = null,
        public ?array $metadata = null,
    ) {}
}

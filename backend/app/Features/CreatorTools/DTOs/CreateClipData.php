<?php

namespace App\Features\CreatorTools\DTOs;

final readonly class CreateClipData
{
    public function __construct(
        public string $title,
        public int $startSeconds,
        public int $endSeconds,
        public string $kind = 'clip',
        public ?string $thumbnailUrl = null,
        public ?array $metadata = null,
    ) {}
}

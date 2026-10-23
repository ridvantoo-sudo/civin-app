<?php

namespace App\Features\LiveStreaming\DTOs;

final readonly class CreateLiveRoomData
{
    public function __construct(
        public int $categoryId,
        public string $title,
        public ?string $description,
        public ?string $thumbnail,
    ) {}
}

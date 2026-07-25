<?php

namespace App\Features\Ai\DTOs;

final readonly class TranslateData
{
    public function __construct(
        public string $text,
        public string $targetLocale,
        public ?string $sourceLocale = null,
    ) {}
}

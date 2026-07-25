<?php

namespace App\Features\Games\DTOs;

final readonly class MatchActionData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $action,
        public array $payload = [],
    ) {}
}

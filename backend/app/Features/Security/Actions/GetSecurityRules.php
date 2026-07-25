<?php

namespace App\Features\Security\Actions;

use App\Features\Security\Services\SecurityQueryService;

final readonly class GetSecurityRules
{
    public function __construct(private SecurityQueryService $query) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        return $this->query->rules();
    }
}

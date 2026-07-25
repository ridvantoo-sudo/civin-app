<?php

namespace App\Features\Security\Actions;

use App\Features\Security\Services\SecurityQueryService;

final readonly class GetSecurityProfile
{
    public function __construct(private SecurityQueryService $query) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $userId): array
    {
        return $this->query->selfSummary($userId);
    }
}

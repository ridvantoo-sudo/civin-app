<?php

namespace App\Features\Security\Actions;

use App\Features\Security\Services\SecurityQueryService;

final readonly class GetSecurityHistory
{
    public function __construct(private SecurityQueryService $query) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $userId, int $limit = 50): array
    {
        return $this->query->history($userId, $limit);
    }
}

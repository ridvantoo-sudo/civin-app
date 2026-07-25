<?php

namespace App\Features\Ads\Actions;

use App\Features\Ads\Services\AdService;
use App\Features\Users\Models\User;

final readonly class GetAdAnalytics
{
    public function __construct(private AdService $ads) {}

    /** @return array{impressions: int, clicks: int, dismissals: int, rewards: int, ctr: float, events_by_day: list<array{date: string, impressions: int, clicks: int}>} */
    public function execute(User $user, string $id): array
    {
        return $this->ads->analytics($user, $id);
    }
}

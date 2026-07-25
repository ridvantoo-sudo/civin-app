<?php

namespace App\Features\Games\Core\Services;

use App\Features\Games\DTOs\TrackAnalyticsData;
use App\Features\Games\Services\GameService;
use App\Features\Users\Models\User;

final readonly class GameAnalyticsService
{
    public function __construct(private GameService $games) {}

    public function track(User $user, TrackAnalyticsData $data): void
    {
        $this->games->trackAnalytics($user, $data);
    }
}

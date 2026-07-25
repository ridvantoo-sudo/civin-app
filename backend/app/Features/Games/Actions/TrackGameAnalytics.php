<?php

namespace App\Features\Games\Actions;

use App\Features\Games\DTOs\TrackAnalyticsData;
use App\Features\Games\Services\GameService;
use App\Features\Users\Models\User;

final readonly class TrackGameAnalytics
{
    public function __construct(private GameService $games) {}

    public function execute(User $user, TrackAnalyticsData $data): void
    {
        $this->games->trackAnalytics($user, $data);
    }
}

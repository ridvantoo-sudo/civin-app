<?php

namespace App\Features\Ads\Actions;

use App\Features\Ads\DTOs\RecordAdEventData;
use App\Features\Ads\Models\AdEvent;
use App\Features\Ads\Services\AdService;
use App\Features\Users\Models\User;

final readonly class TrackAdEvent
{
    public function __construct(private AdService $ads) {}

    public function execute(User $user, string $id, RecordAdEventData $data): AdEvent
    {
        return $this->ads->track($user, $id, $data);
    }
}

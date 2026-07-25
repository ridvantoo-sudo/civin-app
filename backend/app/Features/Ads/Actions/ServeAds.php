<?php

namespace App\Features\Ads\Actions;

use App\Features\Ads\Models\AdCampaign;
use App\Features\Ads\Services\AdService;
use App\Features\Users\Models\User;
use Illuminate\Support\Collection;

final readonly class ServeAds
{
    public function __construct(private AdService $ads) {}

    /** @return Collection<int, AdCampaign> */
    public function execute(string $placement, ?User $viewer = null, ?string $contextId = null, ?int $limit = null): Collection
    {
        return $this->ads->serve($placement, $viewer, $contextId, $limit);
    }
}

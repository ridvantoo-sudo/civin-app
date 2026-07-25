<?php

namespace App\Features\Ads\Repositories\Contracts;

use App\Features\Ads\DTOs\CreateAdCampaignData;
use App\Features\Ads\DTOs\RecordAdEventData;
use App\Features\Ads\DTOs\UpdateAdCampaignData;
use App\Features\Ads\Models\AdCampaign;
use App\Features\Ads\Models\AdEvent;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AdRepository
{
    public function find(string $id): ?AdCampaign;

    public function findOwned(User $user, string $id): ?AdCampaign;

    /** @return Collection<int, AdCampaign> */
    public function servePlacement(
        string $placement,
        int $limit,
        ?User $viewer = null,
        ?string $contextId = null,
    ): Collection;

    public function paginateOwned(User $user, int $perPage = 20): LengthAwarePaginator;

    public function countActiveOwned(User $user): int;

    public function create(User $owner, CreateAdCampaignData $data, bool $asAdmin = false): AdCampaign;

    public function update(AdCampaign $campaign, UpdateAdCampaignData $data): AdCampaign;

    public function setStatus(AdCampaign $campaign, string $status): AdCampaign;

    public function delete(AdCampaign $campaign): void;

    public function recordEvent(AdCampaign $campaign, ?User $viewer, RecordAdEventData $data): AdEvent;
}

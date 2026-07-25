<?php

namespace App\Features\Discovery\Repositories\Contracts;

use App\Features\Discovery\DTOs\DiscoveryCriteria;
use App\Features\LiveStreaming\Models\LiveCategory;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DiscoveryRepository
{
    public function trendingLive(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator;

    public function trendingVoice(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator;

    public function popularHosts(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator;

    public function nearby(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator;

    public function newHosts(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator;

    public function recommended(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator;

    public function onlineUsers(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator;

    public function search(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator;

    /** @return Collection<int, LiveCategory> */
    public function categories(): Collection;

    public function excludeBlockedUserIds(User $viewer): array;
}

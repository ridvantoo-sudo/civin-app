<?php

namespace App\Features\Discovery\Services;

use App\Features\Discovery\DTOs\DiscoveryCriteria;
use App\Features\Discovery\Repositories\Contracts\DiscoveryRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class DiscoveryService
{
    public function __construct(private DiscoveryRepository $discovery) {}

    public function trendingLive(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        return $this->discovery->trendingLive($viewer, $criteria);
    }

    public function trendingVoice(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        return $this->discovery->trendingVoice($viewer, $criteria);
    }

    public function popularHosts(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        return $this->discovery->popularHosts($viewer, $criteria);
    }

    public function nearby(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        return $this->discovery->nearby($viewer, $criteria);
    }

    public function newHosts(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        return $this->discovery->newHosts($viewer, $criteria);
    }

    public function recommended(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        return $this->discovery->recommended($viewer, $criteria);
    }

    public function onlineUsers(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        return $this->discovery->onlineUsers($viewer, $criteria);
    }

    public function search(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        return $this->discovery->search($viewer, $criteria);
    }

    public function categories(): Collection
    {
        return $this->discovery->categories();
    }

    /**
     * Compact home feed: first page of each Discovery section.
     *
     * @return array<string, mixed>
     */
    public function feed(User $viewer, int $limit = 10): array
    {
        $criteria = new DiscoveryCriteria(perPage: $limit);

        return [
            'trending_live' => $this->trendingLive($viewer, $criteria),
            'trending_voice' => $this->trendingVoice($viewer, $criteria),
            'popular_hosts' => $this->popularHosts($viewer, $criteria),
            'nearby' => $this->nearby($viewer, $criteria),
            'new_hosts' => $this->newHosts($viewer, $criteria),
            'recommended' => $this->recommended($viewer, $criteria),
            'online_users' => $this->onlineUsers($viewer, $criteria),
            'categories' => $this->categories(),
        ];
    }
}

<?php

namespace App\Features\Ads\Actions;

use App\Features\Ads\Services\AdService;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListMyAds
{
    public function __construct(private AdService $ads) {}

    public function execute(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->ads->mine($user, $perPage);
    }
}

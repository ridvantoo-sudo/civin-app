<?php

namespace App\Features\Ads\Actions;

use App\Features\Ads\Services\AdService;
use App\Features\Users\Models\User;

final readonly class DeleteAd
{
    public function __construct(private AdService $ads) {}

    public function execute(User $user, string $id): void
    {
        $this->ads->delete($user, $id);
    }
}

<?php

namespace App\Features\Followers\Actions;

use App\Features\Followers\DTOs\FollowUserData;
use App\Features\Followers\Models\Follow;
use App\Features\Followers\Services\FollowerService;
use App\Features\Users\Models\User;

final readonly class FollowUser
{
    public function __construct(private FollowerService $followers) {}

    public function execute(User $actor, FollowUserData $data): Follow
    {
        $target = User::query()->findOrFail($data->userId);

        return $this->followers->follow($actor, $target);
    }
}

<?php

namespace App\Features\Levels\Actions;

use App\Features\Levels\Services\LevelService;
use App\Features\Users\Models\User;
use Illuminate\Support\Collection;

final readonly class ListBadges
{
    public function __construct(private LevelService $levels) {}

    public function execute(User $user): Collection
    {
        return $this->levels->listBadges($user);
    }
}

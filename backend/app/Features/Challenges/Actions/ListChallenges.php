<?php

namespace App\Features\Challenges\Actions;

use App\Features\Challenges\Services\ChallengeService;
use App\Features\Users\Models\User;
use Illuminate\Support\Collection;

final readonly class ListChallenges
{
    public function __construct(private ChallengeService $challenges) {}

    public function execute(User $user, ?string $period = null): Collection
    {
        return $this->challenges->listChallenges($user, $period);
    }
}

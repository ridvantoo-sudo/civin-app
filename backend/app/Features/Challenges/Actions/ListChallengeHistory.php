<?php

namespace App\Features\Challenges\Actions;

use App\Features\Challenges\Services\ChallengeService;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListChallengeHistory
{
    public function __construct(private ChallengeService $challenges) {}

    public function execute(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return $this->challenges->history($user, $perPage);
    }
}

<?php

namespace App\Features\Challenges\Actions;

use App\Features\Challenges\DTOs\ChallengeClaimResult;
use App\Features\Challenges\Services\ChallengeService;
use App\Features\Users\Models\User;

final readonly class ClaimChallenge
{
    public function __construct(private ChallengeService $challenges) {}

    public function execute(User $user, string $challengeId): ChallengeClaimResult
    {
        return $this->challenges->claim($user, $challengeId);
    }
}

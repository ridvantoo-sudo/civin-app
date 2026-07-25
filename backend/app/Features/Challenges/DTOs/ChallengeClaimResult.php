<?php

namespace App\Features\Challenges\DTOs;

use App\Features\Challenges\Models\UserChallengeProgress;
use App\Features\Levels\Models\UserLevelProgress;
use App\Features\Wallet\Models\Wallet;

final readonly class ChallengeClaimResult
{
    /**
     * @param  array<string, mixed>  $challenge
     */
    public function __construct(
        public bool $claimed,
        public array $challenge,
        public UserChallengeProgress $progress,
        public int $xpAwarded,
        public int $coinsAwarded,
        public int $diamondsAwarded,
        public ?UserLevelProgress $levelProgress,
        public ?Wallet $wallet,
        public bool $leveledUp,
        public int $levelsGained,
    ) {}
}

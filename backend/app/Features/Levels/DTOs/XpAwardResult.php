<?php

namespace App\Features\Levels\DTOs;

use App\Features\Levels\Models\UserLevelProgress;
use App\Features\Levels\Models\XpTransaction;

final readonly class XpAwardResult
{
    public function __construct(
        public UserLevelProgress $progress,
        public ?XpTransaction $transaction,
        public bool $awarded,
        public bool $leveledUp,
        public int $levelsGained,
        public int $amount,
    ) {}
}

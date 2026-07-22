<?php

namespace App\Features\Ranking\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RankingsCalculated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $type,
        public readonly string $period,
        public readonly int $entryCount,
    ) {}
}

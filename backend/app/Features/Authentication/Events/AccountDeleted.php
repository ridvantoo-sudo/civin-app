<?php

namespace App\Features\Authentication\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class AccountDeleted
{
    use Dispatchable;

    public function __construct(public string $userId) {}
}

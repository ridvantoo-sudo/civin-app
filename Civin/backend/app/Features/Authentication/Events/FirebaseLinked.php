<?php

namespace App\Features\Authentication\Events;

use App\Features\Users\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class FirebaseLinked
{
    use Dispatchable;

    public function __construct(public User $user) {}
}

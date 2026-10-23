<?php

namespace App\Features\Authentication\Events;

use App\Features\Users\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UserRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly User $user) {}
}

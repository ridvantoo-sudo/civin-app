<?php

namespace App\Features\SocialEngagement\Events;

use App\Features\SocialEngagement\Models\UserCheckIn;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UserCheckedIn
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public UserCheckIn $checkIn,
    ) {}
}

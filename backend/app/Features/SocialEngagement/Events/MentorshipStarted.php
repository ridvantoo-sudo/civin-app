<?php

namespace App\Features\SocialEngagement\Events;

use App\Features\SocialEngagement\Models\Mentorship;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MentorshipStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Mentorship $mentorship) {}
}

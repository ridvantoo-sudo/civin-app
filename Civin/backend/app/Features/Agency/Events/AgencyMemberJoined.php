<?php

namespace App\Features\Agency\Events;

use App\Features\Agency\Models\AgencyMember;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AgencyMemberJoined
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly AgencyMember $member) {}
}

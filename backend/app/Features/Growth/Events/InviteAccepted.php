<?php

namespace App\Features\Growth\Events;

use App\Features\Growth\Models\Invite;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InviteAccepted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Invite $invite) {}
}

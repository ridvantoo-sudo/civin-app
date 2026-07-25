<?php

namespace App\Features\Svip\Events;

use App\Features\Svip\Models\UserSvip;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SvipActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly UserSvip $subscription) {}
}

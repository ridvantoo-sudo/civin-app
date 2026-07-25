<?php

namespace App\Features\Svip\Events;

use App\Features\Svip\Models\UserSvip;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SvipExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly UserSvip $subscription) {}
}

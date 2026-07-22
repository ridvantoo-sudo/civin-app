<?php

namespace App\Features\Vip\Events;

use App\Features\Vip\Models\UserVip;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class VipExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly UserVip $subscription) {}
}

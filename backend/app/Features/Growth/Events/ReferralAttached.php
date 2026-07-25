<?php

namespace App\Features\Growth\Events;

use App\Features\Growth\Models\ReferralAttribution;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReferralAttached
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ReferralAttribution $attribution) {}
}

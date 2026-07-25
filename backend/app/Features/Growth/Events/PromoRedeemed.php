<?php

namespace App\Features\Growth\Events;

use App\Features\Growth\Models\PromoRedemption;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PromoRedeemed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public PromoRedemption $redemption) {}
}

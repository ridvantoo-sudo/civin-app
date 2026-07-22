<?php

namespace App\Features\Agency\Events;

use App\Features\Agency\Models\AgencyCommission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AgencyCommissionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly AgencyCommission $commission) {}
}

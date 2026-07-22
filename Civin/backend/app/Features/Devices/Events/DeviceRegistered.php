<?php

namespace App\Features\Devices\Events;

use App\Features\Devices\Models\Device;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class DeviceRegistered
{
    use Dispatchable;

    public function __construct(public Device $device) {}
}

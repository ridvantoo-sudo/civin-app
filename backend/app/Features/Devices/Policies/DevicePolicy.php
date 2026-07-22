<?php

namespace App\Features\Devices\Policies;

use App\Features\Devices\Models\Device;
use App\Features\Users\Models\User;

final class DevicePolicy
{
    public function delete(User $user, Device $device): bool
    {
        return $device->user_id === $user->id;
    }
}

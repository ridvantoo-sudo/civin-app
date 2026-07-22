<?php

namespace App\Features\Devices\Actions;

use App\Features\Authentication\DTOs\DeviceData;
use App\Features\Devices\Events\DeviceRegistered;
use App\Features\Devices\Models\Device;
use App\Features\Devices\Repositories\Contracts\DeviceRepository;
use App\Features\Users\Models\User;

final readonly class UpsertDevice
{
    public function __construct(private DeviceRepository $devices) {}

    public function execute(User $user, DeviceData $data): Device
    {
        $device = $this->devices->upsert($user, $data);

        event(new DeviceRegistered($device));

        return $device;
    }
}

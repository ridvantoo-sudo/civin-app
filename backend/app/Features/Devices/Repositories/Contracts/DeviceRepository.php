<?php

namespace App\Features\Devices\Repositories\Contracts;

use App\Features\Authentication\DTOs\DeviceData;
use App\Features\Devices\Models\Device;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface DeviceRepository
{
    public function upsert(User $user, DeviceData $data): Device;

    public function forUser(User $user): Collection;

    public function find(string $id): ?Device;

    public function delete(Device $device): void;
}

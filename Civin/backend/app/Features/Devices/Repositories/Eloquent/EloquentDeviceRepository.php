<?php

namespace App\Features\Devices\Repositories\Eloquent;

use App\Features\Authentication\DTOs\DeviceData;
use App\Features\Devices\Models\Device;
use App\Features\Devices\Repositories\Contracts\DeviceRepository;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class EloquentDeviceRepository implements DeviceRepository
{
    public function upsert(User $user, DeviceData $data): Device
    {
        return Device::withTrashed()->updateOrCreate(
            ['user_id' => $user->id, 'device_uuid' => $data->uuid],
            $data->attributes() + ['last_seen_at' => now(), 'deleted_at' => null],
        );
    }

    public function forUser(User $user): Collection
    {
        return $user->devices()->latest('last_seen_at')->get();
    }

    public function find(string $id): ?Device
    {
        return Device::query()->find($id);
    }

    public function delete(Device $device): void
    {
        $device->delete();
    }
}

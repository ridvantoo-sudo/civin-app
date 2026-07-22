<?php

namespace App\Features\Devices\Services;

use App\Features\Authentication\Repositories\Contracts\RefreshTokenRepository;
use App\Features\Devices\Models\Device;
use App\Features\Devices\Repositories\Contracts\DeviceRepository;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class DeviceService
{
    public function __construct(
        private DeviceRepository $devices,
        private RefreshTokenRepository $refreshTokens,
    ) {}

    public function forUser(User $user): Collection
    {
        return $this->devices->forUser($user);
    }

    public function remove(User $user, Device $device): void
    {
        DB::transaction(function () use ($user, $device): void {
            $this->refreshTokens->revokeForDevice($user->id, $device->id);
            $user->tokens()->whereJsonContains('abilities', 'device:'.$device->id)->delete();
            $this->devices->delete($device);
        });
    }
}

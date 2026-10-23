<?php

namespace Database\Factories;

use App\Features\Devices\Models\Device;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_uuid' => Str::uuid(),
            'platform' => fake()->randomElement(['ios', 'android', 'web']),
            'name' => fake()->word().' device',
            'last_seen_at' => now(),
        ];
    }
}

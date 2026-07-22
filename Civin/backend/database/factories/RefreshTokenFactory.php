<?php

namespace Database\Factories;

use App\Features\Authentication\Models\RefreshToken;
use App\Features\Devices\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RefreshTokenFactory extends Factory
{
    protected $model = RefreshToken::class;

    public function definition(): array
    {
        return [
            'family_id' => Str::uuid(),
            'device_id' => Device::factory(),
            'user_id' => fn (array $attributes) => Device::query()->findOrFail($attributes['device_id'])->user_id,
            'token_hash' => hash('sha256', Str::random(96)),
            'expires_at' => now()->addMonth(),
        ];
    }
}

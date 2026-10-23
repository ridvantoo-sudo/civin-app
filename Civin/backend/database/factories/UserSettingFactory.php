<?php

namespace Database\Factories;

use App\Features\Settings\Models\UserSetting;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserSettingFactory extends Factory
{
    protected $model = UserSetting::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => fake()->unique()->slug(2),
            'value' => fake()->boolean(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Features\Settings\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'type' => 'string',
            'value' => fake()->word(),
            'is_public' => false,
        ];
    }
}

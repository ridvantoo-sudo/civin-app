<?php

namespace Database\Factories;

use App\Features\LiveStreaming\Models\LiveCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class LiveCategoryFactory extends Factory
{
    protected $model = LiveCategory::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'icon' => fake()->optional()->imageUrl(128, 128),
            'status' => 'active',
            'sort_order' => 0,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Features\Gifts\Models\GiftCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GiftCategory>
 */
class GiftCategoryFactory extends Factory
{
    protected $model = GiftCategory::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'icon' => fake()->optional()->imageUrl(128, 128),
            'sort_order' => 0,
            'status' => GiftCategory::STATUS_ACTIVE,
        ];
    }
}

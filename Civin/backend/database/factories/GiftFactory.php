<?php

namespace Database\Factories;

use App\Features\Gifts\Models\Gift;
use App\Features\Gifts\Models\GiftCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gift>
 */
class GiftFactory extends Factory
{
    protected $model = Gift::class;

    public function definition(): array
    {
        return [
            'category_id' => GiftCategory::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'icon' => fake()->optional()->imageUrl(128, 128),
            'animation_url' => fake()->url().'/gift.json',
            'coin_price' => fake()->numberBetween(1, 500),
            'status' => Gift::STATUS_ACTIVE,
        ];
    }
}

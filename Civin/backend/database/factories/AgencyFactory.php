<?php

namespace Database\Factories;

use App\Features\Agency\Models\Agency;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    protected $model = Agency::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->sentence(),
            'logo' => 'https://cdn.example.com/agencies/logo.png',
            'commission_rate' => 10,
            'status' => Agency::STATUS_ACTIVE,
            'members_count' => 1,
            'hosts_count' => 0,
            'total_gross_earnings' => 0,
            'total_commission' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Agency::STATUS_INACTIVE,
        ]);
    }
}

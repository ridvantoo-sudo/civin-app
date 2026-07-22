<?php

namespace Database\Factories;

use App\Features\Countries\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        $alpha2 = fake()->unique()->countryCode();

        return [
            'alpha2' => $alpha2,
            'alpha3' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->unique()->country(),
            'phone_code' => '+'.fake()->numberBetween(1, 999),
            'flag_emoji' => null,
            'active' => true,
        ];
    }
}

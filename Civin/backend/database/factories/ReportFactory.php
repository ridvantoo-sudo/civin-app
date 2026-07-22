<?php

namespace Database\Factories;

use App\Features\Reports\Models\Report;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reported_user_id' => User::factory(),
            'category' => fake()->randomElement(['spam', 'harassment', 'impersonation', 'other']),
            'details' => fake()->sentence(),
            'status' => 'pending',
        ];
    }
}

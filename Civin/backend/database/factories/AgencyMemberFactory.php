<?php

namespace Database\Factories;

use App\Features\Agency\Models\Agency;
use App\Features\Agency\Models\AgencyMember;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgencyMember>
 */
class AgencyMemberFactory extends Factory
{
    protected $model = AgencyMember::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'user_id' => User::factory(),
            'role' => AgencyMember::ROLE_HOST,
            'status' => AgencyMember::STATUS_PENDING,
            'message' => fake()->sentence(),
            'applied_at' => now(),
            'reviewed_at' => null,
            'reviewed_by' => null,
            'removed_at' => null,
            'gross_earnings' => 0,
            'commission_paid' => 0,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AgencyMember::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => AgencyMember::ROLE_OWNER,
            'status' => AgencyMember::STATUS_APPROVED,
            'message' => null,
            'reviewed_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AgencyMember::STATUS_PENDING,
            'role' => AgencyMember::ROLE_HOST,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Features\Users\Models\User;
use App\Features\Wallet\Models\OfflineRechargeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfflineRechargeRequest>
 */
class OfflineRechargeRequestFactory extends Factory
{
    protected $model = OfflineRechargeRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'coins' => 500,
            'amount' => 449,
            'currency' => 'USD',
            'reference_number' => 'REF-'.fake()->unique()->numerify('########'),
            'receipt_path' => 'offline-recharges/receipt.jpg',
            'status' => OfflineRechargeRequest::STATUS_PENDING,
            'approved_by' => null,
            'admin_notes' => null,
            'reviewed_at' => null,
            'created_at' => now(),
        ];
    }
}

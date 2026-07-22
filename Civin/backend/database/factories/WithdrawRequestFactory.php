<?php

namespace Database\Factories;

use App\Features\Users\Models\User;
use App\Features\Wallet\Models\WithdrawRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WithdrawRequest>
 */
class WithdrawRequestFactory extends Factory
{
    protected $model = WithdrawRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'diamonds' => 100,
            'amount' => 1000,
            'status' => WithdrawRequest::STATUS_PENDING,
            'approved_by' => null,
            'created_at' => now(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Features\Users\Models\User;
use App\Features\Wallet\Models\RechargeOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RechargeOrder>
 */
class RechargeOrderFactory extends Factory
{
    protected $model = RechargeOrder::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'package_name' => 'Starter Pack',
            'coins' => 500,
            'price' => 499,
            'currency' => 'USD',
            'status' => RechargeOrder::STATUS_PENDING,
            'payment_provider' => 'apple',
            'transaction_id' => 'txn_'.Str::lower(Str::random(24)),
            'created_at' => now(),
        ];
    }
}

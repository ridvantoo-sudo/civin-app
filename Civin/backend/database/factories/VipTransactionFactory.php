<?php

namespace Database\Factories;

use App\Features\Users\Models\User;
use App\Features\Vip\Models\UserVip;
use App\Features\Vip\Models\VipLevel;
use App\Features\Vip\Models\VipTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VipTransaction>
 */
class VipTransactionFactory extends Factory
{
    protected $model = VipTransaction::class;

    public function definition(): array
    {
        $level = VipLevel::factory();

        return [
            'user_id' => User::factory(),
            'vip_level_id' => $level,
            'user_vip_id' => null,
            'type' => VipTransaction::TYPE_PURCHASE,
            'coins' => 500,
            'from_level' => null,
            'to_level' => 1,
            'metadata' => null,
            'created_at' => now(),
        ];
    }

    public function forSubscription(UserVip $subscription): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $subscription->user_id,
            'vip_level_id' => $subscription->vip_level_id,
            'user_vip_id' => $subscription->getKey(),
            'to_level' => $subscription->level?->level ?? $attributes['to_level'] ?? 1,
        ]);
    }
}

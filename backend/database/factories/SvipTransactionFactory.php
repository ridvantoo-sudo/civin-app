<?php

namespace Database\Factories;

use App\Features\Svip\Models\SvipLevel;
use App\Features\Svip\Models\SvipTransaction;
use App\Features\Svip\Models\UserSvip;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SvipTransaction>
 */
class SvipTransactionFactory extends Factory
{
    protected $model = SvipTransaction::class;

    public function definition(): array
    {
        $level = SvipLevel::factory()->create();

        return [
            'user_id' => User::factory(),
            'svip_level_id' => $level->id,
            'user_svip_id' => UserSvip::factory()->create(['svip_level_id' => $level->id])->id,
            'type' => SvipTransaction::TYPE_PURCHASE,
            'coins' => $level->coin_price,
            'from_level' => null,
            'to_level' => $level->level,
            'metadata' => null,
            'created_at' => now(),
        ];
    }
}

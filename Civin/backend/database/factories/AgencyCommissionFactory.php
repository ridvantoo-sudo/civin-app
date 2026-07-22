<?php

namespace Database\Factories;

use App\Features\Agency\Models\Agency;
use App\Features\Agency\Models\AgencyCommission;
use App\Features\Agency\Models\AgencyMember;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgencyCommission>
 */
class AgencyCommissionFactory extends Factory
{
    protected $model = AgencyCommission::class;

    public function definition(): array
    {
        $gross = fake()->numberBetween(100, 5000);
        $rate = 10.0;
        $commission = (int) floor($gross * ($rate / 100));

        return [
            'agency_id' => Agency::factory(),
            'host_id' => User::factory(),
            'agency_member_id' => AgencyMember::factory()->approved(),
            'source_type' => (new GiftTransaction)->getMorphClass(),
            'source_id' => GiftTransaction::factory(),
            'gross_amount' => $gross,
            'commission_rate' => $rate,
            'commission_amount' => $commission,
            'host_net_amount' => $gross - $commission,
            'currency' => AgencyCommission::CURRENCY_DIAMONDS,
            'metadata' => null,
            'created_at' => now(),
        ];
    }
}

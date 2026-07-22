<?php

namespace Database\Seeders;

use App\Features\Countries\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['TR', 'TUR', 'Türkiye', '+90', '🇹🇷'],
            ['US', 'USA', 'United States', '+1', '🇺🇸'],
            ['GB', 'GBR', 'United Kingdom', '+44', '🇬🇧'],
            ['DE', 'DEU', 'Germany', '+49', '🇩🇪'],
            ['FR', 'FRA', 'France', '+33', '🇫🇷'],
            ['NL', 'NLD', 'Netherlands', '+31', '🇳🇱'],
            ['CA', 'CAN', 'Canada', '+1', '🇨🇦'],
            ['AU', 'AUS', 'Australia', '+61', '🇦🇺'],
            ['JP', 'JPN', 'Japan', '+81', '🇯🇵'],
            ['BR', 'BRA', 'Brazil', '+55', '🇧🇷'],
        ];

        foreach ($countries as [$alpha2, $alpha3, $name, $phoneCode, $flag]) {
            Country::withTrashed()->updateOrCreate(
                ['alpha2' => $alpha2],
                compact('alpha3', 'name') + [
                    'phone_code' => $phoneCode,
                    'flag_emoji' => $flag,
                    'active' => true,
                    'deleted_at' => null,
                ],
            );
        }
    }
}

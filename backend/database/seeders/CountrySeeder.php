<?php

namespace Database\Seeders;

use App\Features\Countries\Models\Country;
use App\Features\Countries\Models\CountryRegion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $response = Http::retry(3, 500)
            ->timeout(30)
            ->get('https://raw.githubusercontent.com/mledoze/countries/master/countries.json');

        if (! $response->successful()) {
            throw new RuntimeException(
                'Unable to fetch the complete ISO country dataset.',
            );
        }

        $payload = $response->json();
        if (! is_array($payload) || ! array_is_list($payload)) {
            throw new RuntimeException('The ISO country dataset returned invalid JSON.');
        }

        $countries = collect($payload)
            ->map(function (array $country): array {
                $phoneCode = (string) data_get($country, 'idd.root', '');
                $suffixes = data_get($country, 'idd.suffixes', []);

                if (! is_array($suffixes)) {
                    throw new RuntimeException('The ISO country dataset returned invalid calling-code data.');
                }

                if ($phoneCode === '') {
                    $phoneCode = 'N/A';
                } elseif (count($suffixes) === 1) {
                    $phoneCode .= (string) $suffixes[0];
                }

                return [
                    strtoupper((string) ($country['cca2'] ?? '')),
                    strtoupper((string) ($country['cca3'] ?? '')),
                    (string) data_get($country, 'name.common', ''),
                    $phoneCode,
                    (string) ($country['flag'] ?? ''),
                ];
            })
            ->filter(fn (array $country): bool => $country[0] !== ''
                && $country[1] !== ''
                && $country[2] !== '')
            ->values();

        if ($countries->count() < 240) {
            throw new RuntimeException('The ISO country dataset is incomplete.');
        }

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

        $iraq = Country::query()->where('alpha2', 'IQ')->firstOrFail();
        CountryRegion::withTrashed()->updateOrCreate(
            ['country_id' => $iraq->id, 'code' => 'IQ-KRI'],
            [
                'name' => 'Kurdistan Region of Iraq',
                'active' => true,
                'deleted_at' => null,
            ],
        );
    }
}

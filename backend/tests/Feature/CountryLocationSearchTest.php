<?php

namespace Tests\Feature;

use App\Features\Countries\Models\Country;
use App\Features\Countries\Models\CountryRegion;
use App\Filament\Forms\Components\CountryLocationFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryLocationSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_kurdistan_search_returns_iraq_region_not_all_countries(): void
    {
        $results = CountryLocationFields::search('kurdistan');

        $this->assertNotEmpty($results);
        $this->assertLessThanOrEqual(30, count($results));
        $this->assertTrue(
            collect($results)->contains(fn (string $label): bool => str_contains($label, 'Kurdistan Region of Iraq')),
        );

        $region = CountryRegion::query()->where('code', 'IQ-KRI')->firstOrFail();
        $this->assertArrayHasKey('region:'.$region->id, $results);
        $this->assertStringContainsString('flags/kurdistan.svg', $results['region:'.$region->id]);
        $this->assertNotNull($region->flag_url);
        $this->assertStringContainsString('flags/kurdistan.svg', (string) $region->flag_url);

        $iraq = Country::query()->where('alpha2', 'IQ')->firstOrFail();
        $this->assertSame('IRQ', $iraq->alpha3);
        $this->assertFalse(Country::query()->where('alpha2', 'KU')->exists());
    }

    public function test_short_queries_do_not_dump_all_countries(): void
    {
        $this->assertSame([], CountryLocationFields::search(''));
        $this->assertSame([], CountryLocationFields::search('a'));
    }
}

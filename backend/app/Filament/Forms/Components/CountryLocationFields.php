<?php

namespace App\Filament\Forms\Components;

use App\Features\Countries\Models\Country;
use App\Features\Countries\Models\CountryRegion;
use App\Features\Profiles\Models\Profile;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

final class CountryLocationFields
{
    /**
     * Searchable country/region picker that does not preload every country.
     * Region matches (e.g. "Kurdistan") select the parent ISO country + region.
     *
     * @return list<Forms\Components\Component>
     */
    public static function make(): array
    {
        return [
            Select::make('location_selection')
                ->label('Country')
                ->searchable()
                ->preload(false)
                ->dehydrated(false)
                ->allowHtml()
                ->live()
                ->placeholder('Type to search countries or regions…')
                ->helperText('Search by country or region. Kurdistan Region of Iraq is stored under Iraq (IQ/IRQ).')
                ->getSearchResultsUsing(fn (string $search): array => self::search($search))
                ->getOptionLabelUsing(fn (?string $value): ?HtmlString => self::labelFor($value))
                ->afterStateHydrated(function (Select $component, mixed $state, ?Model $record): void {
                    $profile = $record instanceof Profile ? $record : null;
                    if ($profile?->country_region_id) {
                        $component->state('region:'.$profile->country_region_id);

                        return;
                    }
                    if ($profile?->country_id) {
                        $component->state('country:'.$profile->country_id);
                    }
                })
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    if ($state === null || $state === '') {
                        $set('country_id', null);
                        $set('country_region_id', null);

                        return;
                    }

                    if (str_starts_with($state, 'region:')) {
                        $region = CountryRegion::query()->find(substr($state, 7));
                        $set('country_id', $region?->country_id);
                        $set('country_region_id', $region?->id);

                        return;
                    }

                    if (str_starts_with($state, 'country:')) {
                        $set('country_id', substr($state, 8));
                        $set('country_region_id', null);
                    }
                }),
            Forms\Components\Hidden::make('country_id'),
            Forms\Components\Hidden::make('country_region_id'),
        ];
    }

    /** @return array<string, string> */
    public static function search(string $search): array
    {
        $term = trim($search);
        if ($term === '' || mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.$term.'%';
        $results = [];

        $regions = CountryRegion::query()
            ->with('country')
            ->where('active', true)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like);
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

        foreach ($regions as $region) {
            $countryName = $region->country?->name ?? 'Unknown';
            $results['region:'.$region->id] = self::optionHtml(
                label: "{$region->name} ({$countryName})",
                flagUrl: $region->flag_url,
                flagEmoji: $region->country?->flag_emoji,
            );
        }

        $countries = Country::query()
            ->where('active', true)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('alpha2', 'like', $like)
                    ->orWhere('alpha3', 'like', $like);
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        foreach ($countries as $country) {
            $key = 'country:'.$country->id;
            if (isset($results[$key])) {
                continue;
            }
            $results[$key] = self::optionHtml(
                label: $country->name,
                flagUrl: null,
                flagEmoji: $country->flag_emoji,
            );
        }

        return $results;
    }

    public static function labelFor(?string $value): ?HtmlString
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (str_starts_with($value, 'region:')) {
            $region = CountryRegion::query()->with('country')->find(substr($value, 7));
            if ($region === null) {
                return null;
            }

            $countryName = $region->country?->name ?? 'Unknown';

            return new HtmlString(self::optionHtml(
                label: "{$region->name} ({$countryName})",
                flagUrl: $region->flag_url,
                flagEmoji: $region->country?->flag_emoji,
            ));
        }

        if (str_starts_with($value, 'country:')) {
            $country = Country::query()->find(substr($value, 8));
            if ($country === null) {
                return null;
            }

            return new HtmlString(self::optionHtml(
                label: $country->name,
                flagUrl: null,
                flagEmoji: $country->flag_emoji,
            ));
        }

        return null;
    }

    private static function optionHtml(string $label, ?string $flagUrl, ?string $flagEmoji): string
    {
        $safeLabel = e($label);

        if (filled($flagUrl)) {
            $safeUrl = e($flagUrl);

            return '<span style="display:inline-flex;align-items:center;gap:.5rem;">'
                .'<img src="'.$safeUrl.'" alt="" style="width:1.4rem;height:1rem;object-fit:cover;border-radius:2px;border:1px solid rgba(0,0,0,.08);" />'
                .'<span>'.$safeLabel.'</span>'
                .'</span>';
        }

        $flag = filled($flagEmoji) ? e($flagEmoji).' ' : '';

        return $flag.$safeLabel;
    }
}

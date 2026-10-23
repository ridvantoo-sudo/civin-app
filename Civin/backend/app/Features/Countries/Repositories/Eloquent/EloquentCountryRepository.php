<?php

namespace App\Features\Countries\Repositories\Eloquent;

use App\Features\Countries\Models\Country;
use App\Features\Countries\Repositories\Contracts\CountryRepository;
use Illuminate\Database\Eloquent\Collection;

final class EloquentCountryRepository implements CountryRepository
{
    public function active(): Collection
    {
        return Country::query()->where('active', true)->orderBy('name')->get();
    }

    public function findActive(string $id): Country
    {
        return Country::query()->where('active', true)->findOrFail($id);
    }
}

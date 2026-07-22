<?php

namespace App\Features\Countries\Http\Controllers;

use App\Features\Countries\Http\Resources\CountryResource;
use App\Features\Countries\Models\Country;
use App\Features\Countries\Services\CountryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CountryController extends Controller
{
    public function __construct(private readonly CountryService $countries) {}

    public function index(): AnonymousResourceCollection
    {
        return CountryResource::collection($this->countries->active());
    }

    public function show(Country $country): CountryResource
    {
        return new CountryResource($this->countries->show($country->id));
    }
}

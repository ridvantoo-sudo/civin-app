<?php

namespace App\Features\Countries\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CountryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->only(['id', 'alpha2', 'alpha3', 'name', 'phone_code', 'flag_emoji']);
    }
}

<?php

namespace App\Features\GeoDiscovery\Http\Requests;

use App\Features\GeoDiscovery\Models\UserLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'precision' => ['sometimes', 'integer', 'min:4', 'max:8'],
            'share_location' => ['sometimes', 'boolean'],
            'show_distance' => ['sometimes', 'boolean'],
            'nearby_visibility' => [
                'sometimes',
                'string',
                Rule::in([
                    UserLocation::VISIBILITY_EVERYONE,
                    UserLocation::VISIBILITY_FOLLOWERS,
                    UserLocation::VISIBILITY_NOBODY,
                ]),
            ],
        ];
    }

    public function latitude(): float
    {
        return (float) $this->validated('latitude');
    }

    public function longitude(): float
    {
        return (float) $this->validated('longitude');
    }

    public function precision(): int
    {
        return (int) ($this->validated('precision') ?? 6);
    }

    public function privacy(): ?array
    {
        $data = array_intersect_key($this->validated(), array_flip([
            'share_location',
            'show_distance',
            'nearby_visibility',
        ]));

        return $data === [] ? null : $data;
    }
}

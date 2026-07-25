<?php

namespace App\Features\GeoDiscovery\Http\Requests;

use App\Features\GeoDiscovery\Models\UserLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateGeoPrivacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
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
            'location_sharing_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function privacy(): array
    {
        $validated = $this->validated();
        $privacy = array_intersect_key($validated, array_flip([
            'share_location',
            'show_distance',
            'nearby_visibility',
        ]));

        if (array_key_exists('location_sharing_enabled', $validated)
            && ! array_key_exists('share_location', $privacy)) {
            $privacy['share_location'] = (bool) $validated['location_sharing_enabled'];
        }

        return $privacy;
    }
}

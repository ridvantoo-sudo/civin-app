<?php

namespace App\Features\GeoDiscovery\Http\Resources;

use App\Features\GeoDiscovery\Models\UserLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserLocation */
final class UserLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = $request->user()?->getKey() === $this->user_id;

        return [
            'share_location' => (bool) $this->share_location,
            'show_distance' => (bool) $this->show_distance,
            'nearby_visibility' => $this->nearby_visibility,
            'geohash_precision' => $this->geohash_precision,
            'located_at' => $this->located_at?->toISOString(),
            'has_location' => $this->located_at !== null,
            // Exact coords only for the owner.
            'latitude' => $isOwner ? $this->latitude : null,
            'longitude' => $isOwner ? $this->longitude : null,
            'geohash' => $isOwner ? $this->geohash : substr((string) $this->geohash, 0, 5),
        ];
    }
}

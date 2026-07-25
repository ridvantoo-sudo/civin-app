<?php

namespace App\Features\GeoDiscovery\Http\Resources;

use App\Features\Countries\Http\Resources\CountryResource;
use App\Features\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class GeoNearbyUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->profile;

        return [
            'id' => $this->id,
            'username' => $this->username,
            'nickname' => $profile?->display_name,
            'avatar_url' => $profile?->avatar_url,
            'cover_image_url' => $profile?->cover_image_url,
            'bio' => $profile?->bio,
            'country' => $profile?->relationLoaded('country') && $profile->country
                ? (new CountryResource($profile->country))->resolve($request)
                : null,
            'level' => $profile?->level,
            'is_vip' => (bool) $profile?->is_vip,
            'is_svip' => (bool) $profile?->is_svip,
            'is_private' => (bool) $profile?->is_private,
            'followers_count' => $profile?->followers_count ?? 0,
            'following_count' => $profile?->following_count ?? 0,
            'likes_count' => $profile?->likes_count ?? 0,
            'is_online' => (bool) $this->socialStatus?->is_online,
            'is_live' => (bool) $this->socialStatus?->is_live,
            'distance_km' => $this->getAttribute('distance_km'),
            'geohash_prefix' => $this->getAttribute('geohash_prefix'),
        ];
    }
}

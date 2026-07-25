<?php

namespace App\Features\GeoDiscovery\Http\Resources;

use App\Features\LiveStreaming\Http\Resources\LiveRoomResource;
use App\Features\Ranking\Http\Resources\RankingEntryResource;
use App\Features\VoiceRoom\Http\Resources\VoiceRoomResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class GeoDiscoveryFeedResource extends JsonResource
{
    /** @param array<string, mixed> $resource */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $feed = $this->resource;

        return [
            'location' => isset($feed['location']) && $feed['location'] !== null
                ? (new UserLocationResource($feed['location']))->resolve($request)
                : null,
            'nearby_users' => GeoNearbyUserResource::collection($feed['nearby_users'])->resolve($request),
            'nearby_live' => GeoNearbyLiveResource::collection($feed['nearby_live'])->resolve($request),
            'country_live' => LiveRoomResource::collection($feed['country_live'])->resolve($request),
            'country_voice' => VoiceRoomResource::collection($feed['country_voice'])->resolve($request),
            'country_rankings' => RankingEntryResource::collection($feed['country_rankings'])->resolve($request),
        ];
    }
}

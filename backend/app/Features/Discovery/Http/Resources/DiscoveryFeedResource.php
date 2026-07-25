<?php

namespace App\Features\Discovery\Http\Resources;

use App\Features\LiveStreaming\Http\Resources\LiveCategoryResource;
use App\Features\LiveStreaming\Http\Resources\LiveRoomResource;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use App\Features\VoiceRoom\Http\Resources\VoiceRoomResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DiscoveryFeedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $feed = $this->resource;

        return [
            'trending_live' => LiveRoomResource::collection($feed['trending_live'])->resolve($request),
            'trending_voice' => VoiceRoomResource::collection($feed['trending_voice'])->resolve($request),
            'popular_hosts' => SocialUserResource::collection($feed['popular_hosts'])->resolve($request),
            'nearby' => SocialUserResource::collection($feed['nearby'])->resolve($request),
            'new_hosts' => SocialUserResource::collection($feed['new_hosts'])->resolve($request),
            'recommended' => SocialUserResource::collection($feed['recommended'])->resolve($request),
            'online_users' => SocialUserResource::collection($feed['online_users'])->resolve($request),
            'categories' => LiveCategoryResource::collection($feed['categories'])->resolve($request),
        ];
    }
}

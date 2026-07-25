<?php

namespace App\Features\Discovery\Http\Controllers;

use App\Features\Discovery\DTOs\DiscoveryCriteria;
use App\Features\Discovery\Http\Requests\DiscoveryListRequest;
use App\Features\Discovery\Http\Resources\DiscoveryFeedResource;
use App\Features\Discovery\Services\DiscoveryService;
use App\Features\LiveStreaming\Http\Resources\LiveCategoryResource;
use App\Features\LiveStreaming\Http\Resources\LiveRoomResource;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use App\Features\VoiceRoom\Http\Resources\VoiceRoomResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DiscoveryController extends Controller
{
    public function __construct(private readonly DiscoveryService $discovery) {}

    public function feed(DiscoveryListRequest $request): JsonResponse
    {
        return response()->json([
            'data' => (new DiscoveryFeedResource(
                $this->discovery->feed($request->user(), min($request->perPage(), 20)),
            ))->resolve($request),
        ]);
    }

    public function trendingLive(DiscoveryListRequest $request): AnonymousResourceCollection
    {
        return LiveRoomResource::collection(
            $this->discovery->trendingLive($request->user(), $this->criteria($request)),
        );
    }

    public function trendingVoice(DiscoveryListRequest $request): AnonymousResourceCollection
    {
        return VoiceRoomResource::collection(
            $this->discovery->trendingVoice($request->user(), $this->criteria($request)),
        );
    }

    public function popularHosts(DiscoveryListRequest $request): AnonymousResourceCollection
    {
        return SocialUserResource::collection(
            $this->discovery->popularHosts($request->user(), $this->criteria($request)),
        );
    }

    public function nearby(DiscoveryListRequest $request): AnonymousResourceCollection
    {
        return SocialUserResource::collection(
            $this->discovery->nearby($request->user(), $this->criteria($request)),
        );
    }

    public function newHosts(DiscoveryListRequest $request): AnonymousResourceCollection
    {
        return SocialUserResource::collection(
            $this->discovery->newHosts($request->user(), $this->criteria($request)),
        );
    }

    public function recommended(DiscoveryListRequest $request): AnonymousResourceCollection
    {
        return SocialUserResource::collection(
            $this->discovery->recommended($request->user(), $this->criteria($request)),
        );
    }

    public function categories(): AnonymousResourceCollection
    {
        return LiveCategoryResource::collection($this->discovery->categories());
    }

    public function search(DiscoveryListRequest $request): AnonymousResourceCollection
    {
        $criteria = $this->criteria($request);
        $type = $criteria->type ?? 'all';
        $results = $this->discovery->search($request->user(), $criteria);

        return match ($type) {
            'live' => LiveRoomResource::collection($results),
            'voice' => VoiceRoomResource::collection($results),
            default => SocialUserResource::collection($results),
        };
    }

    public function online(DiscoveryListRequest $request): AnonymousResourceCollection
    {
        return SocialUserResource::collection(
            $this->discovery->onlineUsers($request->user(), $this->criteria($request)),
        );
    }

    private function criteria(DiscoveryListRequest $request): DiscoveryCriteria
    {
        return new DiscoveryCriteria(
            perPage: $request->perPage(),
            country: $request->country(),
            categoryId: $request->categoryId(),
            query: $request->queryTerm(),
            type: $request->type(),
        );
    }
}

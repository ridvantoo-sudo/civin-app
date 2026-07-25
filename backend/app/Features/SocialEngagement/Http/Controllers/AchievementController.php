<?php

namespace App\Features\SocialEngagement\Http\Controllers;

use App\Features\SocialEngagement\Actions\ManageSocialEngagement;
use App\Features\SocialEngagement\Http\Resources\AchievementResource;
use App\Features\SocialEngagement\Models\Achievement;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AchievementController extends Controller
{
    public function index(Request $request, ManageSocialEngagement $action): AnonymousResourceCollection
    {
        return AchievementResource::collection($action->achievementCatalog($request->user()));
    }

    public function claim(Request $request, Achievement $achievement, ManageSocialEngagement $action): JsonResponse
    {
        $row = $action->claimAchievement($request->user(), $achievement);

        return response()->json([
            'data' => [
                'id' => $row->id,
                'achievement_id' => $row->achievement_id,
                'progress' => $row->progress,
                'unlocked_at' => $row->unlocked_at?->toIso8601String(),
                'claimed_at' => $row->claimed_at?->toIso8601String(),
                'achievement' => $row->relationLoaded('achievement')
                    ? (new AchievementResource($row->achievement))->resolve($request)
                    : null,
            ],
        ]);
    }
}

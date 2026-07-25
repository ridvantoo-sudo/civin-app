<?php

namespace App\Features\SocialEngagement\Http\Controllers;

use App\Features\SocialEngagement\Actions\ManageSocialEngagement;
use App\Features\SocialEngagement\Http\Resources\UserCheckInResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CheckInController extends Controller
{
    public function status(Request $request, ManageSocialEngagement $action): JsonResponse
    {
        $status = $action->checkInStatus($request->user());

        return response()->json([
            'data' => [
                'today' => $status['today'],
                'already_checked_in' => $status['already_checked_in'],
                'current_streak' => $status['current_streak'],
                'next_reward' => $status['next_reward'],
                'calendar' => $status['calendar'],
                'today_check_in' => $status['today_check_in']
                    ? (new UserCheckInResource($status['today_check_in']))->resolve($request)
                    : null,
                'last_check_in' => $status['last_check_in']
                    ? (new UserCheckInResource($status['last_check_in']))->resolve($request)
                    : null,
            ],
        ]);
    }

    public function store(Request $request, ManageSocialEngagement $action): JsonResponse
    {
        $checkIn = $action->checkIn($request->user());

        return response()->json([
            'data' => (new UserCheckInResource($checkIn))->resolve($request),
        ], 201);
    }
}

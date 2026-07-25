<?php

namespace App\Features\CreatorTools\Http\Controllers;

use App\Features\CreatorTools\Actions\ManageCreatorTools;
use App\Features\CreatorTools\Http\Resources\StreamAnalyticsResource;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StreamAnalyticsController extends Controller
{
    public function summary(Request $request, ManageCreatorTools $action): JsonResponse
    {
        return response()->json(['data' => $action->analyticsSummary($request->user())]);
    }

    public function index(Request $request, ManageCreatorTools $action): AnonymousResourceCollection
    {
        return StreamAnalyticsResource::collection(
            $action->analyticsOverview($request->user(), (int) $request->integer('per_page', 20)),
        );
    }

    public function show(Request $request, LiveRoom $room, ManageCreatorTools $action): JsonResponse
    {
        $analytics = $action->roomAnalytics($request->user(), $room);

        return response()->json([
            'data' => (new StreamAnalyticsResource($analytics))->resolve($request),
        ]);
    }
}

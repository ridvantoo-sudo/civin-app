<?php

namespace App\Features\CreatorTools\Http\Controllers;

use App\Features\CreatorTools\Actions\ManageCreatorTools;
use App\Features\CreatorTools\Http\Resources\RtmpIngestResource;
use App\Features\CreatorTools\Models\RtmpIngestCredential;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RtmpIngestController extends Controller
{
    public function show(Request $request, ManageCreatorTools $action): JsonResponse
    {
        $room = null;
        if ($request->filled('room_id')) {
            $room = LiveRoom::query()->findOrFail((string) $request->query('room_id'));
        }

        $credential = $action->activeRtmp($request->user(), $room);

        return response()->json([
            'data' => $credential ? (new RtmpIngestResource($credential))->resolve($request) : null,
        ]);
    }

    public function issue(Request $request, ManageCreatorTools $action): JsonResponse
    {
        $room = null;
        if ($request->filled('room_id')) {
            $room = LiveRoom::query()->findOrFail((string) $request->input('room_id'));
        }

        $credential = $action->issueRtmp($request->user(), $room);

        return response()->json([
            'data' => (new RtmpIngestResource($credential))->resolve($request),
        ], 201);
    }

    public function revoke(Request $request, RtmpIngestCredential $credential, ManageCreatorTools $action): JsonResponse
    {
        $credential = $action->revokeRtmp($request->user(), $credential);

        return response()->json([
            'data' => (new RtmpIngestResource($credential))->resolve($request),
        ]);
    }
}

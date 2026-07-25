<?php

namespace App\Features\CreatorTools\Http\Controllers;

use App\Features\CreatorTools\Actions\ManageCreatorTools;
use App\Features\CreatorTools\Http\Resources\StreamReplayResource;
use App\Features\CreatorTools\Models\StreamReplay;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StreamReplayController extends Controller
{
    public function index(Request $request, ManageCreatorTools $action): AnonymousResourceCollection
    {
        return StreamReplayResource::collection(
            $action->myReplays($request->user(), (int) $request->integer('per_page', 20)),
        );
    }

    public function show(Request $request, StreamReplay $replay, ManageCreatorTools $action): JsonResponse
    {
        $replay = $action->showReplay($request->user(), $replay);

        return response()->json([
            'data' => (new StreamReplayResource($replay))->resolve($request),
        ]);
    }
}

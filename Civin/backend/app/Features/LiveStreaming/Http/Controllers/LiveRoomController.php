<?php

namespace App\Features\LiveStreaming\Http\Controllers;

use App\Features\LiveStreaming\Actions\CreateLiveRoom;
use App\Features\LiveStreaming\Actions\EndLiveRoom;
use App\Features\LiveStreaming\Actions\JoinLiveRoom;
use App\Features\LiveStreaming\Actions\LeaveLiveRoom;
use App\Features\LiveStreaming\Actions\StartLiveRoom;
use App\Features\LiveStreaming\DTOs\CreateLiveRoomData;
use App\Features\LiveStreaming\DTOs\LiveRoomConnectionData;
use App\Features\LiveStreaming\Http\Requests\CreateLiveRoomRequest;
use App\Features\LiveStreaming\Http\Requests\ListLiveRoomsRequest;
use App\Features\LiveStreaming\Http\Resources\LiveRoomResource;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Services\LiveStreamingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class LiveRoomController extends Controller
{
    public function __construct(private readonly LiveStreamingService $live) {}

    public function store(CreateLiveRoomRequest $request, CreateLiveRoom $action): JsonResponse
    {
        $room = $action->execute($request->user(), new CreateLiveRoomData(
            (int) $request->validated('category_id'),
            (string) $request->validated('title'),
            $request->validated('description'),
            $request->validated('thumbnail'),
        ));

        return response()->json([
            'data' => (new LiveRoomResource($room))->resolve($request),
        ], 201);
    }

    public function start(Request $request, LiveRoom $room, StartLiveRoom $action): JsonResponse
    {
        $this->authorize('start', $room);

        return $this->connectionResponse($request, $action->execute($room));
    }

    public function end(LiveRoom $room, EndLiveRoom $action): LiveRoomResource
    {
        $this->authorize('end', $room);

        return new LiveRoomResource($action->execute($room));
    }

    public function index(ListLiveRoomsRequest $request): AnonymousResourceCollection
    {
        return LiveRoomResource::collection($this->live->live($request->perPage()));
    }

    public function show(LiveRoom $room): LiveRoomResource
    {
        $this->authorize('view', $room);

        return new LiveRoomResource($this->live->show($room));
    }

    public function join(Request $request, LiveRoom $room, JoinLiveRoom $action): JsonResponse
    {
        $this->authorize('join', $room);

        return $this->connectionResponse($request, $action->execute($room, $request->user()));
    }

    public function leave(Request $request, LiveRoom $room, LeaveLiveRoom $action): LiveRoomResource
    {
        $this->authorize('leave', $room);

        return new LiveRoomResource($action->execute($room, $request->user()));
    }

    private function connectionResponse(Request $request, LiveRoomConnectionData $result): JsonResponse
    {
        return response()->json([
            'data' => [
                'room' => (new LiveRoomResource($result->room))->resolve($request),
                'rtc' => $result->rtc->toArray(),
            ],
        ]);
    }
}

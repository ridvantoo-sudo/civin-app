<?php

namespace App\Features\PkBattle\Http\Controllers;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\PkBattle\Actions\AcceptPkBattle;
use App\Features\PkBattle\Actions\EndPkBattle;
use App\Features\PkBattle\Actions\RequestPkBattle;
use App\Features\PkBattle\Actions\ShowPkBattle;
use App\Features\PkBattle\Actions\StartPkBattle;
use App\Features\PkBattle\DTOs\RequestPkBattleData;
use App\Features\PkBattle\Http\Requests\RequestPkBattleRequest;
use App\Features\PkBattle\Http\Resources\PkBattleResource;
use App\Features\PkBattle\Models\PkBattle;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PkBattleController extends Controller
{
    public function request(
        RequestPkBattleRequest $request,
        LiveRoom $room,
        RequestPkBattle $action,
    ): JsonResponse {
        $this->authorize('start', $room);

        $battle = $action->execute(
            $room,
            $request->user(),
            new RequestPkBattleData(
                opponentRoomId: (string) $request->validated('opponent_room_id'),
                durationSeconds: $request->durationSeconds(),
            ),
        );

        return response()->json([
            'data' => (new PkBattleResource($battle))->resolve($request),
        ], 201);
    }

    public function accept(Request $request, LiveRoom $room, AcceptPkBattle $action): JsonResponse
    {
        $this->authorize('start', $room);

        $battle = $action->execute($room, $request->user());

        return response()->json([
            'data' => (new PkBattleResource($battle))->resolve($request),
        ]);
    }

    public function start(Request $request, PkBattle $battle, StartPkBattle $action): JsonResponse
    {
        $this->authorize('start', $battle);

        $started = $action->execute($battle, $request->user());

        return response()->json([
            'data' => (new PkBattleResource($started))->resolve($request),
        ]);
    }

    public function end(Request $request, PkBattle $battle, EndPkBattle $action): JsonResponse
    {
        $this->authorize('end', $battle);

        $finished = $action->execute($battle, $request->user());

        return response()->json([
            'data' => (new PkBattleResource($finished))->resolve($request),
        ]);
    }

    public function show(PkBattle $battle, ShowPkBattle $action): PkBattleResource
    {
        $this->authorize('view', $battle);

        return new PkBattleResource($action->execute($battle));
    }
}

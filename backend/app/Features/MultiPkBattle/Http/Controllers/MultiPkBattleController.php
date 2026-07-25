<?php

namespace App\Features\MultiPkBattle\Http\Controllers;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\MultiPkBattle\Models\MultiPkBattle;
use App\Features\MultiPkBattle\Services\MultiPkBattleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MultiPkBattleController extends Controller
{
    public function create(Request $request, LiveRoom $room, MultiPkBattleService $battles): JsonResponse
    {
        $this->authorize('start', $room);
        $validated = $request->validate([
            'mode' => ['required', Rule::in([MultiPkBattle::MODE_TEAM_2V2, MultiPkBattle::MODE_FFA_4])],
            'invited_room_ids' => ['required', 'array', 'size:3'],
            'invited_room_ids.*' => ['required', 'uuid', 'distinct', 'exists:live_rooms,id'],
            'duration_seconds' => ['sometimes', 'integer', 'min:30', 'max:3600'],
            'countdown_seconds' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'invitation_countdown_seconds' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ]);

        $battle = $battles->create(
            $room,
            $request->user(),
            $validated['mode'],
            $validated['invited_room_ids'],
            $validated['duration_seconds'] ?? MultiPkBattle::DEFAULT_DURATION_SECONDS,
            $validated['countdown_seconds'] ?? MultiPkBattle::DEFAULT_COUNTDOWN_SECONDS,
            $validated['invitation_countdown_seconds'] ?? MultiPkBattle::DEFAULT_INVITATION_SECONDS,
        );

        return response()->json(['data' => $battle->toArray()], 201);
    }

    public function accept(
        Request $request,
        LiveRoom $room,
        MultiPkBattle $battle,
        MultiPkBattleService $battles,
    ): JsonResponse {
        $this->authorize('start', $room);

        return response()->json(['data' => $battles->accept($battle, $room, $request->user())->toArray()]);
    }

    public function start(
        Request $request,
        MultiPkBattle $battle,
        MultiPkBattleService $battles,
    ): JsonResponse {
        $this->authorize('start', $battle);

        return response()->json(['data' => $battles->start($battle)->toArray()]);
    }

    public function end(
        Request $request,
        MultiPkBattle $battle,
        MultiPkBattleService $battles,
    ): JsonResponse {
        $this->authorize('end', $battle);

        return response()->json(['data' => $battles->finish($battle)->toArray()]);
    }

    public function show(
        Request $request,
        MultiPkBattle $battle,
        MultiPkBattleService $battles,
    ): JsonResponse {
        $this->authorize('view', $battle);

        return response()->json(['data' => $battles->load($battle)->toArray()]);
    }
}

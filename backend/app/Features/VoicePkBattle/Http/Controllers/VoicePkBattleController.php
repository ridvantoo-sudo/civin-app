<?php

namespace App\Features\VoicePkBattle\Http\Controllers;

use App\Features\VoicePkBattle\Models\VoicePkBattle;
use App\Features\VoicePkBattle\Models\VoicePkTeam;
use App\Features\VoicePkBattle\Services\VoicePkBattleService;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VoicePkBattleController extends Controller
{
    public function request(
        Request $request,
        VoiceRoom $room,
        VoicePkBattleService $battles,
    ): JsonResponse {
        $this->authorize('end', $room);
        $validated = $request->validate([
            'opponent_room_id' => ['required', 'uuid', 'different:room', 'exists:voice_rooms,id'],
            'duration_seconds' => ['sometimes', 'integer', 'min:15', 'max:3600'],
            'countdown_seconds' => ['sometimes', 'integer', 'min:0', 'max:10'],
        ]);
        $opponent = VoiceRoom::query()->findOrFail($validated['opponent_room_id']);
        $battle = $battles->invite(
            $room,
            $opponent,
            $request->user(),
            $validated['duration_seconds'] ?? VoicePkBattle::DEFAULT_DURATION_SECONDS,
            $validated['countdown_seconds'] ?? VoicePkBattle::DEFAULT_COUNTDOWN_SECONDS,
        );

        return response()->json(['data' => $battle->toArray()], 201);
    }

    public function accept(
        Request $request,
        VoiceRoom $room,
        VoicePkBattleService $battles,
    ): JsonResponse {
        $this->authorize('end', $room);
        $team = VoicePkTeam::query()
            ->where('room_id', $room->getKey())
            ->whereHas('battle', fn ($query) => $query->where('status', VoicePkBattle::STATUS_WAITING))
            ->with('battle')
            ->latest()
            ->firstOrFail();

        return response()->json([
            'data' => $battles->accept($team->battle, $room, $request->user())->toArray(),
        ]);
    }

    public function start(VoicePkBattle $battle, VoicePkBattleService $battles): JsonResponse
    {
        $this->authorize('start', $battle);

        return response()->json(['data' => $battles->start($battle)->toArray()]);
    }

    public function end(VoicePkBattle $battle, VoicePkBattleService $battles): JsonResponse
    {
        $this->authorize('end', $battle);

        return response()->json(['data' => $battles->finish($battle)->toArray()]);
    }

    public function show(VoicePkBattle $battle, VoicePkBattleService $battles): JsonResponse
    {
        $this->authorize('view', $battle);

        return response()->json(['data' => $battles->load($battle)->toArray()]);
    }
}

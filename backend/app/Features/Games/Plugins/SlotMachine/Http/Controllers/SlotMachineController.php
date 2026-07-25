<?php

namespace App\Features\Games\Plugins\SlotMachine\Http\Controllers;

use App\Features\Games\Plugins\SlotMachine\Actions\SlotMachineAction;
use App\Features\Games\Plugins\SlotMachine\DTOs\SlotMachineDTO;
use App\Features\Games\Plugins\SlotMachine\Http\Requests\PlaySlotMachineRequest;
use App\Features\Games\Plugins\SlotMachine\Http\Requests\SlotMachineConfigRequest;
use App\Features\Games\Plugins\SlotMachine\Http\Requests\SlotMachineHistoryRequest;
use App\Features\Games\Plugins\SlotMachine\Http\Requests\SlotMachineJackpotRequest;
use App\Features\Games\Plugins\SlotMachine\Http\Requests\SlotMachineLeaderboardRequest;
use App\Features\Games\Plugins\SlotMachine\Http\Requests\SlotMachineStatisticsRequest;
use App\Features\Games\Plugins\SlotMachine\Http\Resources\SlotMachineResource;
use App\Features\Wallet\Http\Resources\WalletResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SlotMachineController extends Controller
{
    public function config(SlotMachineConfigRequest $request, SlotMachineAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->config($request->user()),
        ]);
    }

    public function play(PlaySlotMachineRequest $request, SlotMachineAction $action): JsonResponse
    {
        $result = $action->play(
            $request->user(),
            new SlotMachineDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                gameMode: (string) $request->validated('game_mode'),
                betAmount: (int) $request->validated('bet_amount'),
                spinCount: (int) ($request->validated('spin_count') ?? 1),
                autoSpin: (bool) ($request->validated('auto_spin') ?? false),
                metadata: $request->validated('metadata') ?? [],
                clientSessionId: $request->validated('client_session_id'),
            ),
        );

        return response()->json([
            'data' => [
                'spin' => (new SlotMachineResource($result['spin']))->resolve($request),
                'wallet' => (new WalletResource($result['wallet']))->resolve($request),
                'replay' => $result['replay'],
            ],
        ], $result['replay'] ? 200 : 201);
    }

    public function history(SlotMachineHistoryRequest $request, SlotMachineAction $action): AnonymousResourceCollection
    {
        return SlotMachineResource::collection(
            $action->history(
                $request->user(),
                (int) ($request->validated('per_page') ?? 30),
            ),
        );
    }

    public function leaderboard(SlotMachineLeaderboardRequest $request, SlotMachineAction $action): JsonResponse
    {
        $entries = $action->leaderboard(
            (string) ($request->validated('period') ?? 'daily'),
            (int) ($request->validated('limit') ?? 50),
        );

        return response()->json([
            'data' => $entries->values()->all(),
        ]);
    }

    public function statistics(SlotMachineStatisticsRequest $request, SlotMachineAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->statistics($request->user()),
        ]);
    }

    public function jackpot(SlotMachineJackpotRequest $request, SlotMachineAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->jackpot($request->user()),
        ]);
    }
}

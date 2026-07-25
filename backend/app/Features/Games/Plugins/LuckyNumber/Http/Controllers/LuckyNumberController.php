<?php

namespace App\Features\Games\Plugins\LuckyNumber\Http\Controllers;

use App\Features\Games\Plugins\LuckyNumber\Actions\LuckyNumberAction;
use App\Features\Games\Plugins\LuckyNumber\DTOs\LuckyNumberDTO;
use App\Features\Games\Plugins\LuckyNumber\Http\Requests\LuckyNumberConfigRequest;
use App\Features\Games\Plugins\LuckyNumber\Http\Requests\LuckyNumberDrawsRequest;
use App\Features\Games\Plugins\LuckyNumber\Http\Requests\LuckyNumberHistoryRequest;
use App\Features\Games\Plugins\LuckyNumber\Http\Requests\LuckyNumberJackpotRequest;
use App\Features\Games\Plugins\LuckyNumber\Http\Requests\LuckyNumberLeaderboardRequest;
use App\Features\Games\Plugins\LuckyNumber\Http\Requests\LuckyNumberStatisticsRequest;
use App\Features\Games\Plugins\LuckyNumber\Http\Requests\PlayLuckyNumberRequest;
use App\Features\Games\Plugins\LuckyNumber\Http\Requests\QuickPickLuckyNumberRequest;
use App\Features\Games\Plugins\LuckyNumber\Http\Resources\LuckyNumberResource;
use App\Features\Wallet\Http\Resources\WalletResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class LuckyNumberController extends Controller
{
    public function config(LuckyNumberConfigRequest $request, LuckyNumberAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->config($request->user()),
        ]);
    }

    public function play(PlayLuckyNumberRequest $request, LuckyNumberAction $action): JsonResponse
    {
        $result = $action->play(
            $request->user(),
            new LuckyNumberDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                gameMode: (string) $request->validated('game_mode'),
                betAmount: (int) $request->validated('bet_amount'),
                pickedNumbers: array_map('intval', $request->validated('picked_numbers') ?? []),
                autoPick: (bool) ($request->validated('auto_pick') ?? false),
                repeatTicket: (bool) ($request->validated('repeat_ticket') ?? false),
                metadata: $request->validated('metadata') ?? [],
                clientSessionId: $request->validated('client_session_id'),
            ),
        );

        return response()->json([
            'data' => [
                'ticket' => (new LuckyNumberResource($result['ticket']))->resolve($request),
                'wallet' => (new WalletResource($result['wallet']))->resolve($request),
                'replay' => $result['replay'],
            ],
        ], $result['replay'] ? 200 : 201);
    }

    public function quickPick(QuickPickLuckyNumberRequest $request, LuckyNumberAction $action): JsonResponse
    {
        $result = $action->quickPick(
            $request->user(),
            new LuckyNumberDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                gameMode: (string) $request->validated('game_mode'),
                betAmount: (int) $request->validated('bet_amount'),
                pickedNumbers: array_map('intval', $request->validated('picked_numbers') ?? []),
                autoPick: true,
                repeatTicket: (bool) ($request->validated('repeat_ticket') ?? false),
                metadata: $request->validated('metadata') ?? [],
                clientSessionId: $request->validated('client_session_id'),
            ),
        );

        return response()->json([
            'data' => [
                'ticket' => (new LuckyNumberResource($result['ticket']))->resolve($request),
                'wallet' => (new WalletResource($result['wallet']))->resolve($request),
                'replay' => $result['replay'],
            ],
        ], $result['replay'] ? 200 : 201);
    }

    public function history(LuckyNumberHistoryRequest $request, LuckyNumberAction $action): AnonymousResourceCollection
    {
        return LuckyNumberResource::collection(
            $action->history(
                $request->user(),
                (int) ($request->validated('per_page') ?? 30),
            ),
        );
    }

    public function leaderboard(LuckyNumberLeaderboardRequest $request, LuckyNumberAction $action): JsonResponse
    {
        $entries = $action->leaderboard(
            (string) ($request->validated('period') ?? 'daily'),
            (int) ($request->validated('limit') ?? 50),
        );

        return response()->json([
            'data' => $entries->values()->all(),
        ]);
    }

    public function statistics(LuckyNumberStatisticsRequest $request, LuckyNumberAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->statistics($request->user()),
        ]);
    }

    public function jackpot(LuckyNumberJackpotRequest $request, LuckyNumberAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->jackpot($request->user()),
        ]);
    }

    public function draws(LuckyNumberDrawsRequest $request, LuckyNumberAction $action): JsonResponse
    {
        $paginator = $action->draws(
            $request->user(),
            (int) ($request->validated('per_page') ?? 30),
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}

<?php

namespace App\Features\Games\Plugins\LuckyWheel\Http\Controllers;

use App\Features\Games\Plugins\LuckyWheel\Actions\LuckyWheelAction;
use App\Features\Games\Plugins\LuckyWheel\DTOs\LuckyWheelDTO;
use App\Features\Games\Plugins\LuckyWheel\Http\Requests\LuckyWheelConfigRequest;
use App\Features\Games\Plugins\LuckyWheel\Http\Requests\LuckyWheelHistoryRequest;
use App\Features\Games\Plugins\LuckyWheel\Http\Requests\LuckyWheelLeaderboardRequest;
use App\Features\Games\Plugins\LuckyWheel\Http\Requests\SpinLuckyWheelRequest;
use App\Features\Games\Plugins\LuckyWheel\Http\Resources\LuckyWheelResource;
use App\Features\Wallet\Http\Resources\WalletResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class LuckyWheelController extends Controller
{
    public function config(LuckyWheelConfigRequest $request, LuckyWheelAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->config($request->user()),
        ]);
    }

    public function spin(SpinLuckyWheelRequest $request, LuckyWheelAction $action): JsonResponse
    {
        $result = $action->spin(
            $request->user(),
            new LuckyWheelDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                costCoins: $request->validated('cost_coins') !== null
                    ? (int) $request->validated('cost_coins')
                    : null,
                metadata: $request->validated('metadata') ?? [],
                clientSessionId: $request->validated('client_session_id'),
            ),
        );

        return response()->json([
            'data' => [
                'spin' => (new LuckyWheelResource($result['spin']))->resolve($request),
                'wallet' => (new WalletResource($result['wallet']))->resolve($request),
                'replay' => $result['replay'],
            ],
        ], $result['replay'] ? 200 : 201);
    }

    public function history(LuckyWheelHistoryRequest $request, LuckyWheelAction $action): AnonymousResourceCollection
    {
        return LuckyWheelResource::collection(
            $action->history(
                $request->user(),
                (int) ($request->validated('per_page') ?? 30),
            ),
        );
    }

    public function leaderboard(LuckyWheelLeaderboardRequest $request, LuckyWheelAction $action): JsonResponse
    {
        $entries = $action->leaderboard(
            (string) ($request->validated('period') ?? 'daily'),
            (int) ($request->validated('limit') ?? 50),
        );

        return response()->json([
            'data' => $entries->values()->all(),
        ]);
    }
}

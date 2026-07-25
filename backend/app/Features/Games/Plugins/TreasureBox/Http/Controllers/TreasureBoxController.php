<?php

namespace App\Features\Games\Plugins\TreasureBox\Http\Controllers;

use App\Features\Games\Plugins\TreasureBox\Actions\TreasureBoxAction;
use App\Features\Games\Plugins\TreasureBox\DTOs\CreateTreasureBoxDTO;
use App\Features\Games\Plugins\TreasureBox\DTOs\JoinTreasureBoxDTO;
use App\Features\Games\Plugins\TreasureBox\Http\Requests\CreateTreasureBoxRequest;
use App\Features\Games\Plugins\TreasureBox\Http\Requests\JoinTreasureBoxRequest;
use App\Features\Games\Plugins\TreasureBox\Http\Requests\TreasureBoxConfigRequest;
use App\Features\Games\Plugins\TreasureBox\Http\Requests\TreasureBoxHistoryRequest;
use App\Features\Games\Plugins\TreasureBox\Http\Requests\TreasureBoxLeaderboardRequest;
use App\Features\Games\Plugins\TreasureBox\Http\Requests\TreasureBoxStatisticsRequest;
use App\Features\Games\Plugins\TreasureBox\Http\Resources\TreasureBoxParticipantResource;
use App\Features\Games\Plugins\TreasureBox\Http\Resources\TreasureBoxResource;
use App\Features\Wallet\Http\Resources\WalletResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TreasureBoxController extends Controller
{
    public function config(TreasureBoxConfigRequest $request, TreasureBoxAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->config($request->user()),
        ]);
    }

    public function create(CreateTreasureBoxRequest $request, TreasureBoxAction $action): JsonResponse
    {
        $whitelist = $request->validated('whitelist_user_ids');

        $result = $action->create(
            $request->user(),
            new CreateTreasureBoxDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                type: (string) $request->validated('type'),
                rewardType: (string) $request->validated('reward_type'),
                distributionType: (string) ($request->validated('distribution_type') ?? 'random'),
                visibility: (string) $request->validated('visibility'),
                eligibilityScope: (string) $request->validated('eligibility_scope'),
                totalAmount: (int) $request->validated('total_amount'),
                winnerCount: (int) $request->validated('winner_count'),
                countdownSeconds: (int) $request->validated('countdown_seconds'),
                claimDurationSeconds: (int) $request->validated('claim_duration_seconds'),
                autoStart: (bool) ($request->validated('auto_start') ?? true),
                scheduledAt: $request->validated('scheduled_at'),
                roomType: $request->validated('room_type'),
                roomId: $request->validated('room_id'),
                whitelistUserIds: $whitelist === null ? null : array_map('strval', $whitelist),
                minWatchTimeSeconds: $request->validated('min_watch_time_seconds'),
                minUserLevel: $request->validated('min_user_level'),
                minGiftValue: $request->validated('min_gift_value'),
                message: $request->validated('message'),
                metadata: $request->validated('metadata') ?? [],
            ),
        );

        return response()->json([
            'data' => [
                'treasure' => (new TreasureBoxResource($result['treasure']))->resolve($request),
                'wallet' => (new WalletResource($result['wallet']))->resolve($request),
                'replay' => $result['replay'],
            ],
        ], $result['replay'] ? 200 : 201);
    }

    public function join(JoinTreasureBoxRequest $request, TreasureBoxAction $action): JsonResponse
    {
        $result = $action->join(
            $request->user(),
            new JoinTreasureBoxDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                treasureBoxId: (string) $request->validated('treasure_box_id'),
                metadata: $request->validated('metadata') ?? [],
            ),
        );

        return response()->json([
            'data' => [
                'treasure' => (new TreasureBoxResource($result['treasure']))->resolve($request),
                'participant' => (new TreasureBoxParticipantResource($result['participant']))->resolve($request),
                'wallet' => (new WalletResource($result['wallet']))->resolve($request),
                'replay' => $result['replay'],
            ],
        ], $result['replay'] ? 200 : 201);
    }

    public function history(TreasureBoxHistoryRequest $request, TreasureBoxAction $action): AnonymousResourceCollection
    {
        return TreasureBoxResource::collection(
            $action->history(
                $request->user(),
                (string) ($request->validated('type') ?? 'all'),
                (int) ($request->validated('per_page') ?? 30),
            ),
        );
    }

    public function leaderboard(TreasureBoxLeaderboardRequest $request, TreasureBoxAction $action): JsonResponse
    {
        $entries = $action->leaderboard(
            (string) ($request->validated('period') ?? 'daily'),
            (string) ($request->validated('type') ?? 'winners'),
            (int) ($request->validated('limit') ?? 50),
        );

        return response()->json([
            'data' => $entries->values()->all(),
        ]);
    }

    public function statistics(TreasureBoxStatisticsRequest $request, TreasureBoxAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->statistics($request->user()),
        ]);
    }
}

<?php

namespace App\Features\Challenges\Http\Controllers;

use App\Features\Challenges\Actions\ClaimChallenge;
use App\Features\Challenges\Actions\ListChallengeHistory;
use App\Features\Challenges\Actions\ListChallenges;
use App\Features\Challenges\Http\Requests\ClaimChallengeRequest;
use App\Features\Challenges\Http\Resources\ChallengeHistoryResource;
use App\Features\Challenges\Models\Challenge;
use App\Features\Levels\Http\Resources\LevelProgressResource;
use App\Features\Wallet\Http\Resources\WalletResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ChallengeController extends Controller
{
    public function index(Request $request, ListChallenges $action): JsonResponse
    {
        $period = $request->query('period');
        $period = is_string($period) && $period !== '' ? $period : null;

        return response()->json([
            'data' => $action->execute($request->user(), $period)->values()->all(),
        ]);
    }

    public function daily(Request $request, ListChallenges $action): JsonResponse
    {
        return response()->json([
            'data' => $action->execute($request->user(), Challenge::PERIOD_DAILY)->values()->all(),
        ]);
    }

    public function weekly(Request $request, ListChallenges $action): JsonResponse
    {
        return response()->json([
            'data' => $action->execute($request->user(), Challenge::PERIOD_WEEKLY)->values()->all(),
        ]);
    }

    public function claim(ClaimChallengeRequest $request, ClaimChallenge $action): JsonResponse
    {
        $result = $action->execute(
            $request->user(),
            (string) $request->validated('challenge_id'),
        );

        return response()->json([
            'data' => [
                'claimed' => $result->claimed,
                'xp_awarded' => $result->xpAwarded,
                'coins_awarded' => $result->coinsAwarded,
                'diamonds_awarded' => $result->diamondsAwarded,
                'leveled_up' => $result->leveledUp,
                'levels_gained' => $result->levelsGained,
                'challenge' => $result->challenge,
                'level_progress' => $result->levelProgress !== null
                    ? (new LevelProgressResource($result->levelProgress))->resolve($request)
                    : null,
                'wallet' => $result->wallet !== null
                    ? (new WalletResource($result->wallet))->resolve($request)
                    : null,
            ],
        ]);
    }

    public function history(Request $request, ListChallengeHistory $action): AnonymousResourceCollection
    {
        return ChallengeHistoryResource::collection(
            $action->execute($request->user(), (int) $request->integer('per_page', 30)),
        );
    }
}

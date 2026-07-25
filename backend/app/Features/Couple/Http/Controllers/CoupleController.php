<?php

namespace App\Features\Couple\Http\Controllers;

use App\Features\Couple\DTOs\SendCoupleGiftData;
use App\Features\Couple\DTOs\SendProposalData;
use App\Features\Couple\DTOs\UpdateCoupleProfileData;
use App\Features\Couple\Http\Requests\SendCoupleGiftRequest;
use App\Features\Couple\Http\Requests\SendCoupleProposalRequest;
use App\Features\Couple\Http\Requests\UpdateCoupleProfileRequest;
use App\Features\Couple\Http\Resources\CoupleGiftResource;
use App\Features\Couple\Http\Resources\CoupleProposalResource;
use App\Features\Couple\Http\Resources\CoupleResource;
use App\Features\Couple\Http\Resources\CoupleTaskResource;
use App\Features\Couple\Models\Couple;
use App\Features\Couple\Models\CoupleProposal;
use App\Features\Couple\Services\CoupleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CoupleController extends Controller
{
    public function propose(SendCoupleProposalRequest $request, CoupleService $couples): JsonResponse
    {
        $this->authorize('propose', Couple::class);

        $proposal = $couples->propose(
            $request->user(),
            new SendProposalData(
                userId: (string) $request->validated('user_id'),
                message: $request->validated('message'),
                expiresInHours: $request->validated('expires_in_hours'),
            ),
        );

        return response()->json([
            'data' => (new CoupleProposalResource($proposal))->resolve($request),
        ], 201);
    }

    public function accept(
        Request $request,
        CoupleProposal $proposal,
        CoupleService $couples,
    ): JsonResponse {
        $this->authorize('acceptProposal', $proposal);

        $couple = $couples->accept($proposal, $request->user());

        return response()->json([
            'data' => (new CoupleResource($couple))->resolve($request),
        ]);
    }

    public function reject(
        Request $request,
        CoupleProposal $proposal,
        CoupleService $couples,
    ): JsonResponse {
        $this->authorize('rejectProposal', $proposal);

        $proposal = $couples->reject($proposal, $request->user());

        return response()->json([
            'data' => (new CoupleProposalResource($proposal))->resolve($request),
        ]);
    }

    public function cancel(
        Request $request,
        CoupleProposal $proposal,
        CoupleService $couples,
    ): JsonResponse {
        $this->authorize('cancelProposal', $proposal);

        $proposal = $couples->cancel($proposal, $request->user());

        return response()->json([
            'data' => (new CoupleProposalResource($proposal))->resolve($request),
        ]);
    }

    public function proposals(Request $request, CoupleService $couples): AnonymousResourceCollection
    {
        return CoupleProposalResource::collection($couples->proposals($request->user()));
    }

    public function me(Request $request, CoupleService $couples): JsonResponse
    {
        $couple = $couples->mine($request->user());

        return response()->json([
            'data' => $couple === null ? null : (new CoupleResource($couple))->resolve($request),
        ]);
    }

    public function show(Request $request, Couple $couple, CoupleService $couples): JsonResponse
    {
        $this->authorize('view', $couple);

        return response()->json([
            'data' => (new CoupleResource($couples->show($couple)))->resolve($request),
        ]);
    }

    public function update(
        UpdateCoupleProfileRequest $request,
        Couple $couple,
        CoupleService $couples,
    ): JsonResponse {
        $this->authorize('update', $couple);

        $couple = $couples->updateProfile(
            $couple,
            $request->user(),
            new UpdateCoupleProfileData(
                displayName: $request->validated('display_name'),
                coverUrl: $request->validated('cover_url'),
            ),
        );

        return response()->json([
            'data' => (new CoupleResource($couple))->resolve($request),
        ]);
    }

    public function end(Request $request, Couple $couple, CoupleService $couples): JsonResponse
    {
        $this->authorize('end', $couple);

        $couple = $couples->end($couple, $request->user());

        return response()->json([
            'data' => (new CoupleResource($couple))->resolve($request),
        ]);
    }

    public function rankings(Request $request, CoupleService $couples): AnonymousResourceCollection
    {
        $this->authorize('viewRankings', Couple::class);

        return CoupleResource::collection(
            $couples->rankings((int) $request->integer('per_page', 20)),
        );
    }

    public function gifts(Request $request, Couple $couple, CoupleService $couples): AnonymousResourceCollection
    {
        $this->authorize('gift', $couple);

        return CoupleGiftResource::collection(
            $couples->gifts($couple, (int) $request->integer('per_page', 20)),
        );
    }

    public function sendGift(
        SendCoupleGiftRequest $request,
        Couple $couple,
        CoupleService $couples,
    ): JsonResponse {
        $this->authorize('gift', $couple);

        $gift = $couples->sendGift(
            $couple,
            $request->user(),
            new SendCoupleGiftData(
                giftId: (string) $request->validated('gift_id'),
                quantity: (int) ($request->validated('quantity') ?? 1),
            ),
        );

        return response()->json([
            'data' => (new CoupleGiftResource($gift))->resolve($request),
        ], 201);
    }

    public function tasks(Request $request, Couple $couple, CoupleService $couples): AnonymousResourceCollection
    {
        $this->authorize('tasks', $couple);

        return CoupleTaskResource::collection($couples->tasks($couple));
    }

    public function claimTask(
        Request $request,
        Couple $couple,
        string $task,
        CoupleService $couples,
    ): JsonResponse {
        $this->authorize('tasks', $couple);

        $couples->claimTask($couple, $request->user(), $task);
        $tasks = $couples->tasks($couple);
        $claimed = $tasks->firstWhere('id', $task);

        return response()->json([
            'data' => $claimed === null
                ? ['id' => $task]
                : (new CoupleTaskResource($claimed))->resolve($request),
        ]);
    }

    public function checkIn(Request $request, Couple $couple, CoupleService $couples): JsonResponse
    {
        $this->authorize('tasks', $couple);

        $couple = $couples->checkIn($couple, $request->user());

        return response()->json([
            'data' => (new CoupleResource($couple))->resolve($request),
        ]);
    }

    public function badge(Request $request, Couple $couple, CoupleService $couples): JsonResponse
    {
        $this->authorize('view', $couple);
        $couple = $couples->show($couple);

        return response()->json([
            'data' => [
                'couple_id' => $couple->id,
                'badge_key' => $couple->badge_key,
                'badge_url' => $couple->badge_url,
                'level' => $couple->level,
            ],
        ]);
    }

    public function level(Request $request, Couple $couple, CoupleService $couples): JsonResponse
    {
        $this->authorize('view', $couple);
        $couple = $couples->show($couple);

        return response()->json([
            'data' => [
                'couple_id' => $couple->id,
                'level' => $couple->level,
                'xp' => $couple->xp,
                'xp_to_next_level' => $couple->xp_to_next_level,
                'badge_key' => $couple->badge_key,
                'progress_percent' => $couple->xp_to_next_level > 0
                    ? round(($couple->xp / $couple->xp_to_next_level) * 100, 2)
                    : 0,
            ],
        ]);
    }

    public function anniversary(Request $request, Couple $couple, CoupleService $couples): JsonResponse
    {
        $this->authorize('anniversary', $couple);

        return response()->json([
            'data' => $couples->anniversary($couple),
        ]);
    }

    public function claimAnniversary(
        Request $request,
        Couple $couple,
        CoupleService $couples,
    ): JsonResponse {
        $this->authorize('anniversary', $couple);

        $couple = $couples->claimAnniversary($couple, $request->user());

        return response()->json([
            'data' => [
                'couple' => (new CoupleResource($couple))->resolve($request),
                'anniversary' => $couples->anniversary($couple),
            ],
        ]);
    }
}

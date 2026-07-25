<?php

namespace App\Features\FamilyClub\Http\Controllers;

use App\Features\FamilyClub\DTOs\ContributeWalletData;
use App\Features\FamilyClub\DTOs\CreateFamilyData;
use App\Features\FamilyClub\DTOs\InviteMemberData;
use App\Features\FamilyClub\DTOs\JoinFamilyData;
use App\Features\FamilyClub\DTOs\ReviewMemberData;
use App\Features\FamilyClub\DTOs\SendFamilyGiftData;
use App\Features\FamilyClub\DTOs\SendFamilyMessageData;
use App\Features\FamilyClub\DTOs\UpdateMemberRoleData;
use App\Features\FamilyClub\Http\Requests\ContributeFamilyWalletRequest;
use App\Features\FamilyClub\Http\Requests\CreateFamilyRequest;
use App\Features\FamilyClub\Http\Requests\InviteFamilyMemberRequest;
use App\Features\FamilyClub\Http\Requests\JoinFamilyRequest;
use App\Features\FamilyClub\Http\Requests\ReviewFamilyMemberRequest;
use App\Features\FamilyClub\Http\Requests\SendFamilyGiftRequest;
use App\Features\FamilyClub\Http\Requests\SendFamilyMessageRequest;
use App\Features\FamilyClub\Http\Requests\UpdateFamilyMemberRoleRequest;
use App\Features\FamilyClub\Http\Resources\FamilyGiftResource;
use App\Features\FamilyClub\Http\Resources\FamilyInviteResource;
use App\Features\FamilyClub\Http\Resources\FamilyMemberResource;
use App\Features\FamilyClub\Http\Resources\FamilyMessageResource;
use App\Features\FamilyClub\Http\Resources\FamilyMissionResource;
use App\Features\FamilyClub\Http\Resources\FamilyResource;
use App\Features\FamilyClub\Http\Resources\FamilyWalletTransactionResource;
use App\Features\FamilyClub\Models\Family;
use App\Features\FamilyClub\Services\FamilyService;
use App\Features\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class FamilyController extends Controller
{
    public function store(CreateFamilyRequest $request, FamilyService $families): JsonResponse
    {
        $this->authorize('create', Family::class);

        $family = $families->create(
            $request->user(),
            new CreateFamilyData(
                name: (string) $request->validated('name'),
                description: $request->validated('description'),
                logo: $request->validated('logo'),
            ),
        );

        return response()->json([
            'data' => (new FamilyResource($family))->resolve($request),
        ], 201);
    }

    public function me(Request $request, FamilyService $families): JsonResponse
    {
        $family = $families->mine($request->user());

        return response()->json([
            'data' => $family === null ? null : (new FamilyResource($family))->resolve($request),
        ]);
    }

    public function show(Request $request, Family $family, FamilyService $families): JsonResponse
    {
        $this->authorize('view', $family);

        return response()->json([
            'data' => (new FamilyResource($families->show($family)))->resolve($request),
        ]);
    }

    public function rankings(Request $request, FamilyService $families): AnonymousResourceCollection
    {
        $this->authorize('viewRankings', Family::class);

        $perPage = (int) $request->integer('per_page', 20);

        return FamilyResource::collection($families->rankings($perPage));
    }

    public function invite(
        InviteFamilyMemberRequest $request,
        Family $family,
        FamilyService $families,
    ): JsonResponse {
        $this->authorize('invite', $family);

        $invite = $families->invite(
            $family,
            $request->user(),
            new InviteMemberData(
                userId: $request->validated('user_id'),
                role: (string) ($request->validated('role') ?? 'member'),
                expiresInHours: $request->validated('expires_in_hours'),
            ),
        );

        return response()->json([
            'data' => (new FamilyInviteResource($invite))->resolve($request),
        ], 201);
    }

    public function join(JoinFamilyRequest $request, FamilyService $families): JsonResponse
    {
        $this->authorize('join', Family::class);

        $member = $families->join(
            $request->user(),
            new JoinFamilyData(code: (string) $request->validated('code')),
        );

        return response()->json([
            'data' => (new FamilyMemberResource($member))->resolve($request),
        ], 201);
    }

    public function approve(
        ReviewFamilyMemberRequest $request,
        Family $family,
        FamilyService $families,
    ): JsonResponse {
        $this->authorize('approve', $family);

        $member = $families->approve(
            $family,
            $request->user(),
            new ReviewMemberData(userId: (string) $request->validated('user_id')),
        );

        return response()->json([
            'data' => (new FamilyMemberResource($member))->resolve($request),
        ]);
    }

    public function reject(
        ReviewFamilyMemberRequest $request,
        Family $family,
        FamilyService $families,
    ): JsonResponse {
        $this->authorize('reject', $family);

        $member = $families->reject(
            $family,
            $request->user(),
            new ReviewMemberData(userId: (string) $request->validated('user_id')),
        );

        return response()->json([
            'data' => (new FamilyMemberResource($member))->resolve($request),
        ]);
    }

    public function removeMember(
        Request $request,
        Family $family,
        User $user,
        FamilyService $families,
    ): JsonResponse {
        $this->authorize('removeMember', $family);

        $member = $families->removeMember($family, $request->user(), $user);

        return response()->json([
            'data' => (new FamilyMemberResource($member))->resolve($request),
        ]);
    }

    public function updateRole(
        UpdateFamilyMemberRoleRequest $request,
        Family $family,
        FamilyService $families,
    ): JsonResponse {
        $this->authorize('updateRole', $family);

        $member = $families->updateRole(
            $family,
            $request->user(),
            new UpdateMemberRoleData(
                userId: (string) $request->validated('user_id'),
                role: (string) $request->validated('role'),
            ),
        );

        return response()->json([
            'data' => (new FamilyMemberResource($member))->resolve($request),
        ]);
    }

    public function members(Request $request, Family $family, FamilyService $families): AnonymousResourceCollection
    {
        $this->authorize('viewMembers', $family);

        return FamilyMemberResource::collection($families->members($family));
    }

    public function messages(Request $request, Family $family, FamilyService $families): AnonymousResourceCollection
    {
        $this->authorize('chat', $family);

        return FamilyMessageResource::collection(
            $families->messages($family, (int) $request->integer('per_page', 30)),
        );
    }

    public function sendMessage(
        SendFamilyMessageRequest $request,
        Family $family,
        FamilyService $families,
    ): JsonResponse {
        $this->authorize('chat', $family);

        $message = $families->sendMessage(
            $family,
            $request->user(),
            new SendFamilyMessageData(body: (string) $request->validated('body')),
        );

        return response()->json([
            'data' => (new FamilyMessageResource($message))->resolve($request),
        ], 201);
    }

    public function gifts(Request $request, Family $family, FamilyService $families): AnonymousResourceCollection
    {
        $this->authorize('gift', $family);

        return FamilyGiftResource::collection(
            $families->gifts($family, (int) $request->integer('per_page', 20)),
        );
    }

    public function sendGift(
        SendFamilyGiftRequest $request,
        Family $family,
        FamilyService $families,
    ): JsonResponse {
        $this->authorize('gift', $family);

        $gift = $families->sendGift(
            $family,
            $request->user(),
            new SendFamilyGiftData(
                giftId: (string) $request->validated('gift_id'),
                quantity: (int) ($request->validated('quantity') ?? 1),
            ),
        );

        return response()->json([
            'data' => (new FamilyGiftResource($gift))->resolve($request),
        ], 201);
    }

    public function wallet(Request $request, Family $family, FamilyService $families): JsonResponse
    {
        $this->authorize('wallet', $family);
        $family = $families->show($family);

        return response()->json([
            'data' => [
                'family_id' => $family->id,
                'wallet_coins' => $family->wallet_coins,
                'wallet_diamonds' => $family->wallet_diamonds,
                'transactions' => FamilyWalletTransactionResource::collection(
                    $families->walletTransactions($family, (int) $request->integer('per_page', 20)),
                )->resolve($request),
            ],
        ]);
    }

    public function contribute(
        ContributeFamilyWalletRequest $request,
        Family $family,
        FamilyService $families,
    ): JsonResponse {
        $this->authorize('wallet', $family);

        $tx = $families->contribute(
            $family,
            $request->user(),
            new ContributeWalletData(
                amount: (int) $request->validated('amount'),
                currency: (string) ($request->validated('currency') ?? 'coins'),
                note: $request->validated('note'),
            ),
        );

        return response()->json([
            'data' => (new FamilyWalletTransactionResource($tx))->resolve($request),
        ], 201);
    }

    public function missions(Request $request, Family $family, FamilyService $families): AnonymousResourceCollection
    {
        $this->authorize('missions', $family);

        return FamilyMissionResource::collection($families->missions($family));
    }

    public function claimMission(
        Request $request,
        Family $family,
        string $mission,
        FamilyService $families,
    ): JsonResponse {
        $this->authorize('missions', $family);

        $progress = $families->claimMission($family, $request->user(), $mission);
        $missions = $families->missions($family);
        $claimed = $missions->firstWhere('id', $mission);

        return response()->json([
            'data' => $claimed === null
                ? [
                    'id' => $progress->mission_id,
                    'claimed_at' => $progress->claimed_at?->toISOString(),
                ]
                : (new FamilyMissionResource($claimed))->resolve($request),
        ]);
    }

    public function badge(Request $request, Family $family, FamilyService $families): JsonResponse
    {
        $this->authorize('view', $family);
        $family = $families->show($family);

        return response()->json([
            'data' => [
                'family_id' => $family->id,
                'badge_key' => $family->badge_key,
                'badge_url' => $family->badge_url,
                'level' => $family->level,
            ],
        ]);
    }

    public function level(Request $request, Family $family, FamilyService $families): JsonResponse
    {
        $this->authorize('view', $family);
        $family = $families->show($family);

        return response()->json([
            'data' => [
                'family_id' => $family->id,
                'level' => $family->level,
                'xp' => $family->xp,
                'xp_to_next_level' => $family->xp_to_next_level,
                'badge_key' => $family->badge_key,
                'progress_percent' => $family->xp_to_next_level > 0
                    ? round(($family->xp / $family->xp_to_next_level) * 100, 2)
                    : 0,
            ],
        ]);
    }
}

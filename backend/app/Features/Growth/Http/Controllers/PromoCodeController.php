<?php

namespace App\Features\Growth\Http\Controllers;

use App\Features\Growth\Actions\ManageGrowth;
use App\Features\Growth\DTOs\CreatePromoCodeData;
use App\Features\Growth\DTOs\RedeemPromoData;
use App\Features\Growth\Http\Requests\CreatePromoCodeRequest;
use App\Features\Growth\Http\Requests\RedeemPromoCodeRequest;
use App\Features\Growth\Http\Resources\PromoCodeResource;
use App\Features\Growth\Http\Resources\PromoRedemptionResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PromoCodeController extends Controller
{
    public function store(CreatePromoCodeRequest $request, ManageGrowth $action): JsonResponse
    {
        $v = $request->validated();
        $promo = $action->createPromo(
            $request->user(),
            new CreatePromoCodeData(
                code: (string) $v['code'],
                rewardType: (string) $v['reward_type'],
                rewardValue: (int) $v['reward_value'],
                maxRedemptions: isset($v['max_redemptions']) ? (int) $v['max_redemptions'] : null,
                perUserLimit: (int) ($v['per_user_limit'] ?? 1),
                description: $v['description'] ?? null,
                startsAt: isset($v['starts_at']) ? new \DateTimeImmutable($v['starts_at']) : null,
                endsAt: isset($v['ends_at']) ? new \DateTimeImmutable($v['ends_at']) : null,
                metadata: $v['metadata'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new PromoCodeResource($promo))->resolve($request),
        ], 201);
    }

    public function redeem(RedeemPromoCodeRequest $request, ManageGrowth $action): JsonResponse
    {
        $redemption = $action->redeemPromo(
            $request->user(),
            new RedeemPromoData((string) $request->validated('code')),
        );

        return response()->json([
            'data' => (new PromoRedemptionResource($redemption))->resolve($request),
        ], 201);
    }

    public function validateCode(Request $request, ManageGrowth $action): JsonResponse
    {
        $code = (string) $request->query('code', '');
        $promo = $action->validatePromo($code);

        return response()->json([
            'data' => (new PromoCodeResource($promo))->resolve($request),
        ]);
    }

    public function redemptions(Request $request, ManageGrowth $action): AnonymousResourceCollection
    {
        return PromoRedemptionResource::collection(
            $action->myPromoRedemptions($request->user(), (int) $request->integer('per_page', 20)),
        );
    }
}

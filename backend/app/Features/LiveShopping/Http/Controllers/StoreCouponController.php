<?php

namespace App\Features\LiveShopping\Http\Controllers;

use App\Features\LiveShopping\DTOs\CreateStoreCouponData;
use App\Features\LiveShopping\Http\Requests\CreateStoreCouponRequest;
use App\Features\LiveShopping\Http\Requests\ValidateStoreCouponRequest;
use App\Features\LiveShopping\Http\Resources\StoreCouponResource;
use App\Features\LiveShopping\Models\ShopOrder;
use App\Features\LiveShopping\Models\StoreCoupon;
use App\Features\LiveShopping\Services\StoreCouponService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StoreCouponController extends Controller
{
    public function index(Request $request, StoreCouponService $coupons): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ShopOrder::class);

        return StoreCouponResource::collection(
            $coupons->listForSeller($request->user(), (int) $request->integer('per_page', 20)),
        );
    }

    public function store(CreateStoreCouponRequest $request, StoreCouponService $coupons): JsonResponse
    {
        $this->authorize('checkout', ShopOrder::class);
        $v = $request->validated();

        $coupon = $coupons->create(
            $request->user(),
            new CreateStoreCouponData(
                code: (string) $v['code'],
                type: (string) $v['type'],
                value: (int) $v['value'],
                minOrderCoins: isset($v['min_order_coins']) ? (int) $v['min_order_coins'] : null,
                maxDiscountCoins: isset($v['max_discount_coins']) ? (int) $v['max_discount_coins'] : null,
                maxRedemptions: isset($v['max_redemptions']) ? (int) $v['max_redemptions'] : null,
                perUserLimit: (int) ($v['per_user_limit'] ?? 1),
                startsAt: isset($v['starts_at']) ? new \DateTimeImmutable($v['starts_at']) : null,
                endsAt: isset($v['ends_at']) ? new \DateTimeImmutable($v['ends_at']) : null,
                description: $v['description'] ?? null,
                metadata: $v['metadata'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new StoreCouponResource($coupon))->resolve($request),
        ], 201);
    }

    public function destroy(Request $request, StoreCoupon $coupon, StoreCouponService $coupons): JsonResponse
    {
        $this->authorize('checkout', ShopOrder::class);
        $coupon = $coupons->deactivate($request->user(), $coupon);

        return response()->json([
            'data' => (new StoreCouponResource($coupon))->resolve($request),
        ]);
    }

    public function validateCode(ValidateStoreCouponRequest $request, StoreCouponService $coupons): JsonResponse
    {
        $this->authorize('checkout', ShopOrder::class);
        $v = $request->validated();
        $result = $coupons->validateForBuyer(
            $request->user(),
            (string) $v['code'],
            (string) $v['seller_id'],
            (int) $v['subtotal_coins'],
        );

        return response()->json([
            'data' => [
                'discount_coins' => $result['discount_coins'],
                'payable_coins' => $result['payable_coins'],
                'subtotal_coins' => $result['subtotal_coins'],
                'coupon' => (new StoreCouponResource($result['coupon']))->resolve($request),
            ],
        ]);
    }
}

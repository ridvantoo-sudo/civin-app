<?php

namespace App\Features\Vip\Http\Controllers;

use App\Features\Vip\Actions\GetMyVip;
use App\Features\Vip\Actions\ListVipLevels;
use App\Features\Vip\Actions\PurchaseVip;
use App\Features\Vip\Actions\UpgradeVip;
use App\Features\Vip\DTOs\PurchaseVipData;
use App\Features\Vip\DTOs\UpgradeVipData;
use App\Features\Vip\Http\Requests\PurchaseVipRequest;
use App\Features\Vip\Http\Requests\UpgradeVipRequest;
use App\Features\Vip\Http\Resources\UserVipResource;
use App\Features\Vip\Http\Resources\VipLevelResource;
use App\Features\Vip\Models\UserVip;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class VipController extends Controller
{
    public function levels(ListVipLevels $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', UserVip::class);

        return VipLevelResource::collection($action->execute());
    }

    public function me(Request $request, GetMyVip $action): JsonResponse
    {
        $this->authorize('viewAny', UserVip::class);

        $subscription = $action->execute($request->user());

        if ($subscription === null) {
            return response()->json([
                'data' => [
                    'is_vip' => false,
                    'status' => null,
                    'started_at' => null,
                    'expires_at' => null,
                    'level' => null,
                    'privileges' => null,
                ],
            ]);
        }

        $this->authorize('view', $subscription);

        return response()->json([
            'data' => (new UserVipResource($subscription))->resolve($request),
        ]);
    }

    public function purchase(PurchaseVipRequest $request, PurchaseVip $action): JsonResponse
    {
        $this->authorize('purchase', UserVip::class);

        $subscription = $action->execute(
            $request->user(),
            new PurchaseVipData(
                vipLevelId: (string) $request->validated('vip_level_id'),
                metadata: $request->validated('metadata'),
            ),
        );

        return response()->json([
            'data' => (new UserVipResource($subscription))->resolve($request),
        ], 201);
    }

    public function upgrade(UpgradeVipRequest $request, UpgradeVip $action): JsonResponse
    {
        $this->authorize('upgrade', UserVip::class);

        $subscription = $action->execute(
            $request->user(),
            new UpgradeVipData(
                vipLevelId: (string) $request->validated('vip_level_id'),
                metadata: $request->validated('metadata'),
            ),
        );

        return response()->json([
            'data' => (new UserVipResource($subscription))->resolve($request),
        ], 201);
    }
}

<?php

namespace App\Features\Svip\Http\Controllers;

use App\Features\Svip\Actions\GetMySvip;
use App\Features\Svip\Actions\ListSvipLevels;
use App\Features\Svip\Actions\PurchaseSvip;
use App\Features\Svip\Actions\UpgradeSvip;
use App\Features\Svip\DTOs\PurchaseSvipData;
use App\Features\Svip\DTOs\UpgradeSvipData;
use App\Features\Svip\Http\Requests\PurchaseSvipRequest;
use App\Features\Svip\Http\Requests\UpgradeSvipRequest;
use App\Features\Svip\Http\Resources\SvipLevelResource;
use App\Features\Svip\Http\Resources\UserSvipResource;
use App\Features\Svip\Models\UserSvip;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SvipController extends Controller
{
    public function levels(ListSvipLevels $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', UserSvip::class);

        return SvipLevelResource::collection($action->execute());
    }

    public function me(Request $request, GetMySvip $action): JsonResponse
    {
        $this->authorize('viewAny', UserSvip::class);

        $subscription = $action->execute($request->user());

        if ($subscription === null) {
            return response()->json([
                'data' => [
                    'is_svip' => false,
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
            'data' => (new UserSvipResource($subscription))->resolve($request),
        ]);
    }

    public function purchase(PurchaseSvipRequest $request, PurchaseSvip $action): JsonResponse
    {
        $this->authorize('purchase', UserSvip::class);

        $subscription = $action->execute(
            $request->user(),
            new PurchaseSvipData(
                svipLevelId: (string) $request->validated('svip_level_id'),
                metadata: $request->validated('metadata'),
            ),
        );

        return response()->json([
            'data' => (new UserSvipResource($subscription))->resolve($request),
        ], 201);
    }

    public function upgrade(UpgradeSvipRequest $request, UpgradeSvip $action): JsonResponse
    {
        $this->authorize('upgrade', UserSvip::class);

        $subscription = $action->execute(
            $request->user(),
            new UpgradeSvipData(
                svipLevelId: (string) $request->validated('svip_level_id'),
                metadata: $request->validated('metadata'),
            ),
        );

        return response()->json([
            'data' => (new UserSvipResource($subscription))->resolve($request),
        ], 201);
    }
}

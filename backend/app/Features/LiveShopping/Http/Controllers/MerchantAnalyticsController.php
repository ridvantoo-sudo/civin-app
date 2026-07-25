<?php

namespace App\Features\LiveShopping\Http\Controllers;

use App\Features\LiveShopping\Models\ShopOrder;
use App\Features\LiveShopping\Services\MerchantAnalyticsService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MerchantAnalyticsController extends Controller
{
    public function overview(Request $request, MerchantAnalyticsService $analytics): JsonResponse
    {
        $this->authorize('viewAny', ShopOrder::class);
        [$from, $to] = $this->range($request);

        return response()->json([
            'data' => $analytics->overview($request->user(), $from, $to),
        ]);
    }

    public function products(Request $request, MerchantAnalyticsService $analytics): JsonResponse
    {
        $this->authorize('viewAny', ShopOrder::class);
        [$from, $to] = $this->range($request);

        return response()->json([
            'data' => $analytics->products(
                $request->user(),
                $from,
                $to,
                (int) $request->integer('limit', 20),
            ),
        ]);
    }

    public function timeseries(Request $request, MerchantAnalyticsService $analytics): JsonResponse
    {
        $this->authorize('viewAny', ShopOrder::class);
        [$from, $to] = $this->range($request);

        return response()->json([
            'data' => $analytics->timeseries($request->user(), $from, $to),
        ]);
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function range(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse((string) $request->input('from')) : null;
        $to = $request->filled('to') ? Carbon::parse((string) $request->input('to')) : null;

        return [$from, $to];
    }
}

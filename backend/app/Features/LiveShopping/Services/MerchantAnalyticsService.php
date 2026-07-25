<?php

namespace App\Features\LiveShopping\Services;

use App\Features\LiveShopping\Models\ShopOrder;
use App\Features\LiveShopping\Models\ShopOrderItem;
use App\Features\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class MerchantAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(User $seller, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $this->ensureEligible($seller);
        [$from, $to] = $this->normalizeRange($from, $to);

        $base = ShopOrder::query()
            ->where('seller_id', $seller->getKey())
            ->whereBetween('created_at', [$from, $to]);

        $ordersCount = (clone $base)->count();
        $paid = (clone $base)->where('payment_status', ShopOrder::PAYMENT_PAID);
        $paidOrders = (clone $paid)->count();
        $revenue = (int) (clone $paid)->sum('total_coins');
        $discount = (int) (clone $paid)->sum('discount_coins');
        $subtotal = (int) (clone $paid)->sum(DB::raw('COALESCE(subtotal_coins, total_coins)'));

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'orders_count' => $ordersCount,
            'paid_orders' => $paidOrders,
            'revenue_coins' => $revenue,
            'subtotal_coins' => $subtotal,
            'discount_coins' => $discount,
            'average_order_coins' => $paidOrders > 0 ? (int) floor($revenue / $paidOrders) : 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function products(User $seller, ?Carbon $from = null, ?Carbon $to = null, int $limit = 20): array
    {
        $this->ensureEligible($seller);
        [$from, $to] = $this->normalizeRange($from, $to);
        $limit = max(1, min($limit, 100));

        $rows = ShopOrderItem::query()
            ->select([
                'shop_order_items.product_id',
                'shop_order_items.product_name',
                DB::raw('SUM(shop_order_items.quantity) as units_sold'),
                DB::raw('SUM(shop_order_items.total_price) as revenue_coins'),
            ])
            ->join('shop_orders', 'shop_orders.id', '=', 'shop_order_items.order_id')
            ->where('shop_orders.seller_id', $seller->getKey())
            ->where('shop_orders.payment_status', ShopOrder::PAYMENT_PAID)
            ->whereBetween('shop_orders.created_at', [$from, $to])
            ->groupBy('shop_order_items.product_id', 'shop_order_items.product_name')
            ->orderByDesc('units_sold')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row): array => [
            'product_id' => $row->product_id,
            'product_name' => $row->product_name,
            'units_sold' => (int) $row->units_sold,
            'revenue_coins' => (int) $row->revenue_coins,
        ])->all();
    }

    /**
     * @return list<array{date: string, orders: int, revenue_coins: int}>
     */
    public function timeseries(User $seller, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $this->ensureEligible($seller);
        [$from, $to] = $this->normalizeRange($from, $to);

        $driver = DB::connection()->getDriverName();
        $dateExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', created_at)",
            default => 'DATE(created_at)',
        };

        $rows = ShopOrder::query()
            ->select([
                DB::raw("{$dateExpr} as day"),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total_coins) as revenue_coins'),
            ])
            ->where('seller_id', $seller->getKey())
            ->where('payment_status', ShopOrder::PAYMENT_PAID)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw($dateExpr))
            ->orderBy('day')
            ->get();

        return $rows->map(fn ($row): array => [
            'date' => (string) $row->day,
            'orders' => (int) $row->orders,
            'revenue_coins' => (int) $row->revenue_coins,
        ])->all();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function normalizeRange(?Carbon $from, ?Carbon $to): array
    {
        $to = ($to ?? now())->copy()->endOfDay();
        $from = ($from ?? now()->subDays(29))->copy()->startOfDay();
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered sellers can view analytics.');
        }
    }
}

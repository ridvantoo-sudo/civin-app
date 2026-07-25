<?php

namespace App\Features\LiveShopping\Repositories\Eloquent;

use App\Features\LiveShopping\DTOs\CartItemData;
use App\Features\LiveShopping\DTOs\CheckoutData;
use App\Features\LiveShopping\DTOs\PinProductData;
use App\Features\LiveShopping\DTOs\UpdateShippingData;
use App\Features\LiveShopping\DTOs\UpsertStoreProductData;
use App\Features\LiveShopping\Models\LiveProductPin;
use App\Features\LiveShopping\Models\ShopOrder;
use App\Features\LiveShopping\Models\ShopOrderItem;
use App\Features\LiveShopping\Models\ShoppingCart;
use App\Features\LiveShopping\Models\ShoppingCartItem;
use App\Features\LiveShopping\Models\StoreProduct;
use App\Features\LiveShopping\Repositories\Contracts\LiveShoppingRepository;
use App\Features\LiveShopping\Services\StoreCouponService;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EloquentLiveShoppingRepository implements LiveShoppingRepository
{
    public function __construct(
        private readonly WalletRepository $wallets,
        private readonly StoreCouponService $coupons,
    ) {}

    public function productsForSeller(User $seller, bool $offlineOnly = false): Collection
    {
        $query = StoreProduct::query()
            ->where('seller_id', $seller->getKey())
            ->where('status', StoreProduct::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderByDesc('created_at');

        if ($offlineOnly) {
            $query->where('is_offline_available', true);
        }

        return $query->get();
    }

    public function findProduct(string $productId): ?StoreProduct
    {
        return StoreProduct::query()->whereKey($productId)->first();
    }

    public function createProduct(User $seller, UpsertStoreProductData $data): StoreProduct
    {
        return StoreProduct::query()->create([
            'seller_id' => $seller->getKey(),
            'name' => $data->name,
            'description' => $data->description,
            'image_url' => $data->imageUrl,
            'price_coins' => $data->priceCoins,
            'stock' => $data->stock,
            'status' => StoreProduct::STATUS_ACTIVE,
            'is_offline_available' => $data->isOfflineAvailable,
            'sort_order' => $data->sortOrder,
            'metadata' => $data->metadata,
        ]);
    }

    public function updateProduct(StoreProduct $product, UpsertStoreProductData $data): StoreProduct
    {
        $product->fill([
            'name' => $data->name,
            'description' => $data->description,
            'image_url' => $data->imageUrl,
            'price_coins' => $data->priceCoins,
            'stock' => $data->stock,
            'is_offline_available' => $data->isOfflineAvailable,
            'sort_order' => $data->sortOrder,
            'metadata' => $data->metadata,
        ]);
        $product->save();

        return $product->fresh();
    }

    public function deactivateProduct(StoreProduct $product): StoreProduct
    {
        $product->status = StoreProduct::STATUS_INACTIVE;
        $product->save();

        LiveProductPin::query()
            ->where('product_id', $product->getKey())
            ->where('is_pinned', true)
            ->update(['is_pinned' => false]);

        return $product->fresh();
    }

    public function pinsForRoom(LiveRoom $room): Collection
    {
        return LiveProductPin::query()
            ->where('room_id', $room->getKey())
            ->where('is_pinned', true)
            ->with('product')
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (LiveProductPin $pin): bool => $pin->product !== null && $pin->product->status === StoreProduct::STATUS_ACTIVE)
            ->values();
    }

    public function pinProduct(LiveRoom $room, User $host, PinProductData $data): LiveProductPin
    {
        $product = StoreProduct::query()
            ->whereKey($data->productId)
            ->where('seller_id', $host->getKey())
            ->where('status', StoreProduct::STATUS_ACTIVE)
            ->first();

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => 'Product not found in your store.',
            ]);
        }

        $pin = LiveProductPin::query()->updateOrCreate(
            [
                'room_id' => $room->getKey(),
                'product_id' => $product->getKey(),
            ],
            [
                'pinned_by' => $host->getKey(),
                'is_pinned' => $data->isPinned,
                'flash_deal_price' => $data->flashDealPrice,
                'flash_deal_ends_at' => $data->flashDealEndsAt,
                'sort_order' => $data->sortOrder,
            ],
        );

        return $pin->fresh('product');
    }

    public function unpinProduct(LiveProductPin $pin): LiveProductPin
    {
        $pin->is_pinned = false;
        $pin->save();

        return $pin->fresh('product');
    }

    public function updatePin(LiveProductPin $pin, PinProductData $data): LiveProductPin
    {
        $pin->fill([
            'is_pinned' => $data->isPinned,
            'flash_deal_price' => $data->flashDealPrice,
            'flash_deal_ends_at' => $data->flashDealEndsAt,
            'sort_order' => $data->sortOrder,
        ]);
        $pin->save();

        return $pin->fresh('product');
    }

    public function findPin(string $pinId): ?LiveProductPin
    {
        return LiveProductPin::query()->with('product')->whereKey($pinId)->first();
    }

    public function cartForUser(User $user): ShoppingCart
    {
        $cart = ShoppingCart::query()->firstOrCreate(['user_id' => $user->getKey()]);

        return $cart->load(['items.product', 'items.livePin']);
    }

    public function addCartItem(User $user, CartItemData $data): ShoppingCart
    {
        return DB::transaction(function () use ($user, $data): ShoppingCart {
            $cart = ShoppingCart::query()->lockForUpdate()->firstOrCreate(['user_id' => $user->getKey()]);
            if ($data->roomId !== null) {
                $cart->room_id = $data->roomId;
                $cart->save();
            }

            $product = StoreProduct::query()->lockForUpdate()->find($data->productId);
            if ($product === null || ! $product->isPurchasable()) {
                throw ValidationException::withMessages(['product_id' => 'Product is unavailable.']);
            }

            $unitPrice = (int) $product->price_coins;
            $pinId = $data->livePinId;
            if ($pinId !== null) {
                $pin = LiveProductPin::query()->with('product')->find($pinId);
                if ($pin === null || ! $pin->is_pinned || $pin->product_id !== $product->getKey()) {
                    throw ValidationException::withMessages(['live_pin_id' => 'Pinned product is unavailable.']);
                }
                $unitPrice = $pin->effectivePrice();
            }

            if ($data->quantity < 1) {
                throw ValidationException::withMessages(['quantity' => 'Quantity must be at least 1.']);
            }

            if ($data->quantity > $product->stock) {
                throw ValidationException::withMessages(['quantity' => 'Not enough stock available.']);
            }

            $item = ShoppingCartItem::query()
                ->where('cart_id', $cart->getKey())
                ->where('product_id', $product->getKey())
                ->first();

            if ($item === null) {
                ShoppingCartItem::query()->create([
                    'cart_id' => $cart->getKey(),
                    'product_id' => $product->getKey(),
                    'live_pin_id' => $pinId,
                    'quantity' => $data->quantity,
                    'unit_price' => $unitPrice,
                ]);
            } else {
                $newQty = $item->quantity + $data->quantity;
                if ($newQty > $product->stock) {
                    throw ValidationException::withMessages(['quantity' => 'Not enough stock available.']);
                }
                $item->quantity = $newQty;
                $item->unit_price = $unitPrice;
                $item->live_pin_id = $pinId ?? $item->live_pin_id;
                $item->save();
            }

            return $this->cartForUser($user);
        });
    }

    public function updateCartItemQuantity(User $user, string $itemId, int $quantity): ShoppingCart
    {
        return DB::transaction(function () use ($user, $itemId, $quantity): ShoppingCart {
            $cart = $this->lockCart($user);
            $item = ShoppingCartItem::query()
                ->where('cart_id', $cart->getKey())
                ->whereKey($itemId)
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                throw ValidationException::withMessages(['item' => 'Cart item not found.']);
            }

            if ($quantity < 1) {
                $item->delete();

                return $this->cartForUser($user);
            }

            $product = StoreProduct::query()->lockForUpdate()->find($item->product_id);
            if ($product === null || $quantity > $product->stock) {
                throw ValidationException::withMessages(['quantity' => 'Not enough stock available.']);
            }

            $item->quantity = $quantity;
            $item->save();

            return $this->cartForUser($user);
        });
    }

    public function removeCartItem(User $user, string $itemId): ShoppingCart
    {
        return DB::transaction(function () use ($user, $itemId): ShoppingCart {
            $cart = $this->lockCart($user);
            ShoppingCartItem::query()
                ->where('cart_id', $cart->getKey())
                ->whereKey($itemId)
                ->delete();

            return $this->cartForUser($user);
        });
    }

    public function clearCart(User $user): ShoppingCart
    {
        return DB::transaction(function () use ($user): ShoppingCart {
            $cart = $this->lockCart($user);
            ShoppingCartItem::query()->where('cart_id', $cart->getKey())->delete();
            $cart->room_id = null;
            $cart->save();

            return $this->cartForUser($user);
        });
    }

    public function checkout(User $buyer, CheckoutData $data): ShopOrder
    {
        return DB::transaction(function () use ($buyer, $data): ShopOrder {
            $lockedBuyer = User::query()->lockForUpdate()->findOrFail($buyer->getKey());
            $lines = $this->resolveCheckoutLines($lockedBuyer, $data);

            if ($lines === []) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $sellerId = $lines[0]['seller_id'];
            foreach ($lines as $line) {
                if ($line['seller_id'] !== $sellerId) {
                    throw ValidationException::withMessages([
                        'cart' => 'Checkout supports one seller at a time.',
                    ]);
                }
                if ($line['seller_id'] === $lockedBuyer->getKey()) {
                    throw ValidationException::withMessages(['cart' => 'You cannot buy your own products.']);
                }
            }

            $subtotal = array_sum(array_map(fn (array $line): int => $line['total'], $lines));
            $applied = $this->coupons->resolveForCheckout(
                $lockedBuyer,
                $data->couponCode,
                $sellerId,
                $subtotal,
            );
            $discount = $applied['discount_coins'] ?? 0;
            $payable = max(0, $subtotal - $discount);
            $now = now();

            $order = ShopOrder::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'buyer_id' => $lockedBuyer->getKey(),
                'seller_id' => $sellerId,
                'room_id' => $data->roomId,
                'status' => ShopOrder::STATUS_PENDING,
                'payment_status' => ShopOrder::PAYMENT_PENDING,
                'payment_method' => $data->paymentMethod,
                'payment_provider' => $data->paymentProvider,
                'payment_transaction_id' => $data->paymentTransactionId,
                'total_coins' => $payable,
                'subtotal_coins' => $subtotal,
                'discount_coins' => $discount,
                'store_coupon_id' => $applied !== null ? $applied['coupon']->getKey() : null,
                'coupon_code' => $applied !== null ? $applied['coupon']->code : null,
                'shipping_name' => $data->shippingName,
                'shipping_phone' => $data->shippingPhone,
                'shipping_address_line' => $data->shippingAddressLine,
                'shipping_city' => $data->shippingCity,
                'shipping_country' => $data->shippingCountry,
                'shipping_postal_code' => $data->shippingPostalCode,
                'metadata' => $data->metadata,
            ]);

            foreach ($lines as $line) {
                ShopOrderItem::query()->create([
                    'order_id' => $order->getKey(),
                    'product_id' => $line['product_id'],
                    'product_name' => $line['product_name'],
                    'product_image_url' => $line['product_image_url'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'total_price' => $line['total'],
                ]);

                $product = StoreProduct::query()->lockForUpdate()->findOrFail($line['product_id']);
                if ($product->stock < $line['quantity']) {
                    throw ValidationException::withMessages([
                        'stock' => "Insufficient stock for {$product->name}.",
                    ]);
                }
                $product->decrement('stock', $line['quantity']);
            }

            if ($applied !== null) {
                $this->coupons->recordRedemption(
                    $applied['coupon'],
                    $lockedBuyer,
                    $order,
                    $discount,
                );
            }

            if ($data->paymentMethod === ShopOrder::METHOD_WALLET) {
                $this->chargeWallet($lockedBuyer, $order, $payable, $now);
            } elseif ($data->paymentMethod === ShopOrder::METHOD_EXTERNAL) {
                if ($data->paymentProvider === null || $data->paymentTransactionId === null) {
                    // Pending external payment — stock reserved, await confirmation.
                    $order->payment_status = ShopOrder::PAYMENT_PENDING;
                    $order->save();
                } else {
                    $this->markPaid($order, $data->paymentProvider, $data->paymentTransactionId, $now);
                }
            } else {
                throw ValidationException::withMessages([
                    'payment_method' => 'Unsupported payment method.',
                ]);
            }

            if ($data->clearCart && $data->items === null) {
                $cart = ShoppingCart::query()->where('user_id', $lockedBuyer->getKey())->first();
                if ($cart !== null) {
                    ShoppingCartItem::query()->where('cart_id', $cart->getKey())->delete();
                    $cart->room_id = null;
                    $cart->save();
                }
            }

            return $order->fresh(['items', 'buyer.profile', 'seller.profile']);
        });
    }

    public function completeExternalPayment(ShopOrder $order, string $provider, string $transactionId): ShopOrder
    {
        return DB::transaction(function () use ($order, $provider, $transactionId): ShopOrder {
            $locked = ShopOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($locked->isPaid()) {
                return $locked->fresh(['items', 'buyer.profile', 'seller.profile']);
            }

            if ($locked->payment_method !== ShopOrder::METHOD_EXTERNAL) {
                throw ValidationException::withMessages(['payment' => 'Order is not an external payment.']);
            }

            $this->markPaid($locked, $provider, $transactionId, now());

            return $locked->fresh(['items', 'buyer.profile', 'seller.profile']);
        });
    }

    public function ordersForBuyer(User $buyer, int $perPage): LengthAwarePaginator
    {
        return ShopOrder::query()
            ->where('buyer_id', $buyer->getKey())
            ->with(['items', 'seller.profile'])
            ->orderByDesc('created_at')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function ordersForSeller(User $seller, int $perPage): LengthAwarePaginator
    {
        return ShopOrder::query()
            ->where('seller_id', $seller->getKey())
            ->with(['items', 'buyer.profile'])
            ->orderByDesc('created_at')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function findOrder(string $orderId): ?ShopOrder
    {
        return ShopOrder::query()
            ->with(['items', 'buyer.profile', 'seller.profile'])
            ->whereKey($orderId)
            ->first();
    }

    public function updateShipping(ShopOrder $order, UpdateShippingData $data): ShopOrder
    {
        if (! in_array($data->status, ShopOrder::shippingStatuses(), true)) {
            throw ValidationException::withMessages(['status' => 'Invalid shipping status.']);
        }

        if (! $order->isPaid() && $data->status !== ShopOrder::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['status' => 'Order must be paid before shipping updates.']);
        }

        $order->status = $data->status;
        if ($data->trackingNumber !== null) {
            $order->tracking_number = $data->trackingNumber;
        }

        if ($data->status === ShopOrder::STATUS_SHIPPED && $order->shipped_at === null) {
            $order->shipped_at = now();
        }

        if ($data->status === ShopOrder::STATUS_DELIVERED && $order->delivered_at === null) {
            $order->delivered_at = now();
            if ($order->shipped_at === null) {
                $order->shipped_at = now();
            }
        }

        if ($data->status === ShopOrder::STATUS_PROCESSING && $order->status === ShopOrder::STATUS_PENDING) {
            // no-op timestamps
        }

        $order->save();

        return $order->fresh(['items', 'buyer.profile', 'seller.profile']);
    }

    private function lockCart(User $user): ShoppingCart
    {
        return ShoppingCart::query()
            ->lockForUpdate()
            ->firstOrCreate(['user_id' => $user->getKey()]);
    }

    /**
     * @return list<array{product_id: string, product_name: string, product_image_url: ?string, quantity: int, unit_price: int, total: int, seller_id: string}>
     */
    private function resolveCheckoutLines(User $buyer, CheckoutData $data): array
    {
        if ($data->items !== null && $data->items !== []) {
            $lines = [];
            foreach ($data->items as $raw) {
                $productId = (string) ($raw['product_id'] ?? '');
                $quantity = (int) ($raw['quantity'] ?? 0);
                $pinId = isset($raw['live_pin_id']) ? (string) $raw['live_pin_id'] : null;
                $product = StoreProduct::query()->lockForUpdate()->find($productId);
                if ($product === null || ! $product->isPurchasable()) {
                    throw ValidationException::withMessages(['items' => 'One or more products are unavailable.']);
                }
                if ($quantity < 1 || $quantity > $product->stock) {
                    throw ValidationException::withMessages(['items' => 'Invalid quantity for '.$product->name]);
                }
                $unitPrice = (int) $product->price_coins;
                if ($pinId !== null && $pinId !== '') {
                    $pin = LiveProductPin::query()->find($pinId);
                    if ($pin !== null && $pin->is_pinned && $pin->product_id === $product->getKey()) {
                        $unitPrice = $pin->effectivePrice();
                    }
                }
                $lines[] = [
                    'product_id' => $product->getKey(),
                    'product_name' => $product->name,
                    'product_image_url' => $product->image_url,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $unitPrice * $quantity,
                    'seller_id' => $product->seller_id,
                ];
            }

            return $lines;
        }

        $cart = $this->cartForUser($buyer);
        $lines = [];
        foreach ($cart->items as $item) {
            $product = StoreProduct::query()->lockForUpdate()->find($item->product_id);
            if ($product === null || ! $product->isPurchasable()) {
                throw ValidationException::withMessages(['cart' => 'A cart item is no longer available.']);
            }
            if ($item->quantity > $product->stock) {
                throw ValidationException::withMessages(['cart' => "Insufficient stock for {$product->name}."]);
            }
            $unitPrice = (int) $item->unit_price;
            if ($item->livePin !== null && $item->livePin->is_pinned) {
                $unitPrice = $item->livePin->effectivePrice();
            }
            $lines[] = [
                'product_id' => $product->getKey(),
                'product_name' => $product->name,
                'product_image_url' => $product->image_url,
                'quantity' => $item->quantity,
                'unit_price' => $unitPrice,
                'total' => $unitPrice * $item->quantity,
                'seller_id' => $product->seller_id,
            ];
        }

        return $lines;
    }

    private function chargeWallet(User $buyer, ShopOrder $order, int $total, $now): void
    {
        $wallet = $this->wallets->lockForUser($buyer);
        if ($wallet->coins_balance < $total) {
            throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
        }

        $wallet->decrement('coins_balance', $total);

        WalletTransaction::query()->create([
            'user_id' => $buyer->getKey(),
            'type' => WalletTransaction::TYPE_LIVE_SHOPPING_PURCHASE,
            'amount' => -$total,
            'currency' => WalletTransaction::CURRENCY_COINS,
            'reference_type' => $order->getMorphClass(),
            'reference_id' => $order->getKey(),
            'metadata' => [
                'order_number' => $order->order_number,
                'seller_id' => $order->seller_id,
                'room_id' => $order->room_id,
            ],
            'created_at' => $now,
        ]);

        $order->payment_status = ShopOrder::PAYMENT_PAID;
        $order->status = ShopOrder::STATUS_PROCESSING;
        $order->paid_at = $now;
        $order->save();
    }

    private function markPaid(ShopOrder $order, string $provider, string $transactionId, $now): void
    {
        $exists = ShopOrder::query()
            ->where('payment_provider', $provider)
            ->where('payment_transaction_id', $transactionId)
            ->whereKeyNot($order->getKey())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'payment_transaction_id' => 'This payment transaction was already used.',
            ]);
        }

        $order->payment_provider = $provider;
        $order->payment_transaction_id = $transactionId;
        $order->payment_status = ShopOrder::PAYMENT_PAID;
        $order->status = ShopOrder::STATUS_PROCESSING;
        $order->paid_at = $now;
        $order->save();
    }

    private function generateOrderNumber(): string
    {
        return 'LS-'.strtoupper(Str::random(10));
    }
}

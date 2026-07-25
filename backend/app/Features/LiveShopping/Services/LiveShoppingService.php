<?php

namespace App\Features\LiveShopping\Services;

use App\Features\LiveShopping\DTOs\CartItemData;
use App\Features\LiveShopping\DTOs\CheckoutData;
use App\Features\LiveShopping\DTOs\PinProductData;
use App\Features\LiveShopping\DTOs\UpdateShippingData;
use App\Features\LiveShopping\DTOs\UpsertStoreProductData;
use App\Features\LiveShopping\Events\LiveProductPinned;
use App\Features\LiveShopping\Events\LiveProductUnpinned;
use App\Features\LiveShopping\Events\ShopOrderPlaced;
use App\Features\LiveShopping\Events\ShopOrderShippingUpdated;
use App\Features\LiveShopping\Models\LiveProductPin;
use App\Features\LiveShopping\Models\ShopOrder;
use App\Features\LiveShopping\Models\ShoppingCart;
use App\Features\LiveShopping\Models\StoreProduct;
use App\Features\LiveShopping\Repositories\Contracts\LiveShoppingRepository;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class LiveShoppingService
{
    private const CHECKOUT_RATE_KEY = 'live-shopping-checkout:%s';

    private const RATE_MAX = 15;

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private LiveShoppingRepository $shopping,
        private WalletRepository $wallets,
    ) {}

    public function myProducts(User $seller): Collection
    {
        $this->ensureEligible($seller);

        return $this->shopping->productsForSeller($seller);
    }

    public function offlineStore(User $seller): Collection
    {
        return $this->shopping->productsForSeller($seller, offlineOnly: true);
    }

    public function createProduct(User $seller, UpsertStoreProductData $data): StoreProduct
    {
        $this->ensureEligible($seller);

        return $this->shopping->createProduct($seller, $data);
    }

    public function updateProduct(User $seller, StoreProduct $product, UpsertStoreProductData $data): StoreProduct
    {
        $this->ensureEligible($seller);
        $this->ensureOwnsProduct($seller, $product);

        return $this->shopping->updateProduct($product, $data);
    }

    public function removeProduct(User $seller, StoreProduct $product): StoreProduct
    {
        $this->ensureEligible($seller);
        $this->ensureOwnsProduct($seller, $product);

        return $this->shopping->deactivateProduct($product);
    }

    public function roomPins(LiveRoom $room): Collection
    {
        return $this->shopping->pinsForRoom($room);
    }

    public function pin(User $host, LiveRoom $room, PinProductData $data): LiveProductPin
    {
        $this->ensureEligible($host);
        $this->ensureHost($host, $room);

        $pin = $this->shopping->pinProduct($room, $host, $data);
        LiveProductPinned::dispatch($pin);

        return $pin;
    }

    public function unpin(User $host, LiveRoom $room, LiveProductPin $pin): LiveProductPin
    {
        $this->ensureEligible($host);
        $this->ensureHost($host, $room);

        if ($pin->room_id !== $room->getKey()) {
            throw ValidationException::withMessages(['pin' => 'Pin does not belong to this room.']);
        }

        $pin = $this->shopping->unpinProduct($pin);
        LiveProductUnpinned::dispatch($pin);

        return $pin;
    }

    public function updatePin(User $host, LiveRoom $room, LiveProductPin $pin, PinProductData $data): LiveProductPin
    {
        $this->ensureEligible($host);
        $this->ensureHost($host, $room);

        if ($pin->room_id !== $room->getKey()) {
            throw ValidationException::withMessages(['pin' => 'Pin does not belong to this room.']);
        }

        $pin = $this->shopping->updatePin($pin, $data);
        LiveProductPinned::dispatch($pin);

        return $pin;
    }

    public function cart(User $user): ShoppingCart
    {
        $this->ensureEligible($user);

        return $this->shopping->cartForUser($user);
    }

    public function addToCart(User $user, CartItemData $data): ShoppingCart
    {
        $this->ensureEligible($user);

        return $this->shopping->addCartItem($user, $data);
    }

    public function updateCartItem(User $user, string $itemId, int $quantity): ShoppingCart
    {
        $this->ensureEligible($user);

        return $this->shopping->updateCartItemQuantity($user, $itemId, $quantity);
    }

    public function removeFromCart(User $user, string $itemId): ShoppingCart
    {
        $this->ensureEligible($user);

        return $this->shopping->removeCartItem($user, $itemId);
    }

    public function clearCart(User $user): ShoppingCart
    {
        $this->ensureEligible($user);

        return $this->shopping->clearCart($user);
    }

    public function checkout(User $buyer, CheckoutData $data): ShopOrder
    {
        $this->ensureEligible($buyer);
        $this->enforceRateLimit(sprintf(self::CHECKOUT_RATE_KEY, $buyer->getKey()));

        if (! in_array($data->paymentMethod, [ShopOrder::METHOD_WALLET, ShopOrder::METHOD_EXTERNAL], true)) {
            throw ValidationException::withMessages(['payment_method' => 'Invalid payment method.']);
        }

        $order = $this->shopping->checkout($buyer, $data);
        ShopOrderPlaced::dispatch($order);

        if ($data->paymentMethod === ShopOrder::METHOD_WALLET && $order->isPaid()) {
            WalletUpdated::dispatch($this->wallets->findOrCreateForUser($buyer)->fresh());
        }

        return $order;
    }

    public function completeExternalPayment(User $buyer, ShopOrder $order, string $provider, string $transactionId): ShopOrder
    {
        $this->ensureEligible($buyer);

        if ($order->buyer_id !== $buyer->getKey()) {
            throw new AuthorizationException('You can only pay for your own orders.');
        }

        $order = $this->shopping->completeExternalPayment($order, $provider, $transactionId);
        ShopOrderPlaced::dispatch($order);

        return $order;
    }

    public function buyerOrders(User $buyer, int $perPage = 20): LengthAwarePaginator
    {
        $this->ensureEligible($buyer);

        return $this->shopping->ordersForBuyer($buyer, $perPage);
    }

    public function sellerOrders(User $seller, int $perPage = 20): LengthAwarePaginator
    {
        $this->ensureEligible($seller);

        return $this->shopping->ordersForSeller($seller, $perPage);
    }

    public function order(User $user, ShopOrder $order): ShopOrder
    {
        $this->ensureEligible($user);

        if ($order->buyer_id !== $user->getKey() && $order->seller_id !== $user->getKey()) {
            throw new AuthorizationException('You cannot view this order.');
        }

        return $order->loadMissing(['items', 'buyer.profile', 'seller.profile']);
    }

    public function updateShipping(User $seller, ShopOrder $order, UpdateShippingData $data): ShopOrder
    {
        $this->ensureEligible($seller);

        if ($order->seller_id !== $seller->getKey()) {
            throw new AuthorizationException('Only the seller can update shipping status.');
        }

        $order = $this->shopping->updateShipping($order, $data);
        ShopOrderShippingUpdated::dispatch($order);

        return $order;
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can use live shopping.');
        }
    }

    private function ensureOwnsProduct(User $seller, StoreProduct $product): void
    {
        if ($product->seller_id !== $seller->getKey()) {
            throw new AuthorizationException('You do not own this product.');
        }
    }

    private function ensureHost(User $user, LiveRoom $room): void
    {
        if ($room->host_id !== $user->getKey()) {
            throw new AuthorizationException('Only the live host can manage pinned products.');
        }
    }

    private function enforceRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, self::RATE_MAX)) {
            throw ValidationException::withMessages([
                'checkout' => 'You are checking out too quickly.',
            ]);
        }

        RateLimiter::hit($key, self::RATE_DECAY_SECONDS);
    }
}

<?php

namespace App\Features\LiveShopping\Repositories\Contracts;

use App\Features\LiveShopping\DTOs\CartItemData;
use App\Features\LiveShopping\DTOs\CheckoutData;
use App\Features\LiveShopping\DTOs\PinProductData;
use App\Features\LiveShopping\DTOs\UpdateShippingData;
use App\Features\LiveShopping\DTOs\UpsertStoreProductData;
use App\Features\LiveShopping\Models\LiveProductPin;
use App\Features\LiveShopping\Models\ShopOrder;
use App\Features\LiveShopping\Models\ShoppingCart;
use App\Features\LiveShopping\Models\StoreProduct;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface LiveShoppingRepository
{
    public function productsForSeller(User $seller, bool $offlineOnly = false): Collection;

    public function findProduct(string $productId): ?StoreProduct;

    public function createProduct(User $seller, UpsertStoreProductData $data): StoreProduct;

    public function updateProduct(StoreProduct $product, UpsertStoreProductData $data): StoreProduct;

    public function deactivateProduct(StoreProduct $product): StoreProduct;

    public function pinsForRoom(LiveRoom $room): Collection;

    public function pinProduct(LiveRoom $room, User $host, PinProductData $data): LiveProductPin;

    public function unpinProduct(LiveProductPin $pin): LiveProductPin;

    public function updatePin(LiveProductPin $pin, PinProductData $data): LiveProductPin;

    public function findPin(string $pinId): ?LiveProductPin;

    public function cartForUser(User $user): ShoppingCart;

    public function addCartItem(User $user, CartItemData $data): ShoppingCart;

    public function updateCartItemQuantity(User $user, string $itemId, int $quantity): ShoppingCart;

    public function removeCartItem(User $user, string $itemId): ShoppingCart;

    public function clearCart(User $user): ShoppingCart;

    public function checkout(User $buyer, CheckoutData $data): ShopOrder;

    public function completeExternalPayment(ShopOrder $order, string $provider, string $transactionId): ShopOrder;

    public function ordersForBuyer(User $buyer, int $perPage): LengthAwarePaginator;

    public function ordersForSeller(User $seller, int $perPage): LengthAwarePaginator;

    public function findOrder(string $orderId): ?ShopOrder;

    public function updateShipping(ShopOrder $order, UpdateShippingData $data): ShopOrder;
}

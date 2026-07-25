<?php

namespace App\Features\LiveShopping\Actions;

use App\Features\LiveShopping\DTOs\CartItemData;
use App\Features\LiveShopping\DTOs\CheckoutData;
use App\Features\LiveShopping\DTOs\UpdateShippingData;
use App\Features\LiveShopping\Models\ShopOrder;
use App\Features\LiveShopping\Models\ShoppingCart;
use App\Features\LiveShopping\Services\LiveShoppingService;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ManageCartAndOrders
{
    public function __construct(private LiveShoppingService $shopping) {}

    public function cart(User $user): ShoppingCart
    {
        return $this->shopping->cart($user);
    }

    public function addItem(User $user, CartItemData $data): ShoppingCart
    {
        return $this->shopping->addToCart($user, $data);
    }

    public function updateItem(User $user, string $itemId, int $quantity): ShoppingCart
    {
        return $this->shopping->updateCartItem($user, $itemId, $quantity);
    }

    public function removeItem(User $user, string $itemId): ShoppingCart
    {
        return $this->shopping->removeFromCart($user, $itemId);
    }

    public function clear(User $user): ShoppingCart
    {
        return $this->shopping->clearCart($user);
    }

    public function checkout(User $user, CheckoutData $data): ShopOrder
    {
        return $this->shopping->checkout($user, $data);
    }

    public function payExternal(User $user, ShopOrder $order, string $provider, string $txnId): ShopOrder
    {
        return $this->shopping->completeExternalPayment($user, $order, $provider, $txnId);
    }

    public function buyerOrders(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->shopping->buyerOrders($user, $perPage);
    }

    public function sellerOrders(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->shopping->sellerOrders($user, $perPage);
    }

    public function show(User $user, ShopOrder $order): ShopOrder
    {
        return $this->shopping->order($user, $order);
    }

    public function updateShipping(User $user, ShopOrder $order, UpdateShippingData $data): ShopOrder
    {
        return $this->shopping->updateShipping($user, $order, $data);
    }
}

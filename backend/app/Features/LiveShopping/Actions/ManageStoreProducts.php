<?php

namespace App\Features\LiveShopping\Actions;

use App\Features\LiveShopping\DTOs\UpsertStoreProductData;
use App\Features\LiveShopping\Models\StoreProduct;
use App\Features\LiveShopping\Services\LiveShoppingService;
use App\Features\Users\Models\User;
use Illuminate\Support\Collection;

final readonly class ManageStoreProducts
{
    public function __construct(private LiveShoppingService $shopping) {}

    public function list(User $seller): Collection
    {
        return $this->shopping->myProducts($seller);
    }

    public function offline(User $seller): Collection
    {
        return $this->shopping->offlineStore($seller);
    }

    public function create(User $seller, UpsertStoreProductData $data): StoreProduct
    {
        return $this->shopping->createProduct($seller, $data);
    }

    public function update(User $seller, StoreProduct $product, UpsertStoreProductData $data): StoreProduct
    {
        return $this->shopping->updateProduct($seller, $product, $data);
    }

    public function remove(User $seller, StoreProduct $product): StoreProduct
    {
        return $this->shopping->removeProduct($seller, $product);
    }
}

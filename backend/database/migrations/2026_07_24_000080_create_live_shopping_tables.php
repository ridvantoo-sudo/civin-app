<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->unsignedBigInteger('price_coins');
            $table->unsignedInteger('stock')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->boolean('is_offline_available')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'status']);
            $table->index(['seller_id', 'is_offline_available', 'status']);
        });

        Schema::create('live_product_pins', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->foreignUuid('pinned_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_pinned')->default(true)->index();
            $table->unsignedBigInteger('flash_deal_price')->nullable();
            $table->timestamp('flash_deal_ends_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['room_id', 'product_id']);
            $table->index(['room_id', 'is_pinned', 'sort_order']);
        });

        Schema::create('shopping_carts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('live_rooms')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shopping_cart_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cart_id')->constrained('shopping_carts')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->foreignUuid('live_pin_id')->nullable()->constrained('live_product_pins')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price');
            $table->timestamps();
            $table->unique(['cart_id', 'product_id']);
        });

        Schema::create('shop_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('order_number', 32)->unique();
            $table->foreignUuid('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('live_rooms')->nullOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->string('payment_status', 24)->default('pending')->index();
            $table->string('payment_method', 24)->default('wallet');
            $table->string('payment_provider', 64)->nullable();
            $table->string('payment_transaction_id', 191)->nullable();
            $table->unsignedBigInteger('total_coins');
            $table->string('shipping_name', 120)->nullable();
            $table->string('shipping_phone', 40)->nullable();
            $table->string('shipping_address_line', 255)->nullable();
            $table->string('shipping_city', 120)->nullable();
            $table->string('shipping_country', 120)->nullable();
            $table->string('shipping_postal_code', 32)->nullable();
            $table->string('tracking_number', 120)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['buyer_id', 'created_at']);
            $table->index(['seller_id', 'status', 'created_at']);
            $table->unique(['payment_provider', 'payment_transaction_id']);
        });

        Schema::create('shop_order_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('store_products')->nullOnDelete();
            $table->string('product_name', 160);
            $table->string('product_image_url', 2048)->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('total_price');
            $table->timestamps();
            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');
        Schema::dropIfExists('shopping_cart_items');
        Schema::dropIfExists('shopping_carts');
        Schema::dropIfExists('live_product_pins');
        Schema::dropIfExists('store_products');
    }
};

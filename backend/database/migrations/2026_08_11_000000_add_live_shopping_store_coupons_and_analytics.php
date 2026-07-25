<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_coupons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('type', 24); // percent|fixed_coins
            $table->unsignedBigInteger('value');
            $table->unsignedBigInteger('min_order_coins')->nullable();
            $table->unsignedBigInteger('max_discount_coins')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->boolean('active')->default(true)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('description', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['seller_id', 'code']);
            $table->index(['seller_id', 'active']);
        });

        Schema::create('store_coupon_redemptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_coupon_id')->constrained('store_coupons')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('shop_order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('discount_coins');
            $table->timestamps();

            $table->unique(['store_coupon_id', 'shop_order_id']);
            $table->index(['user_id', 'store_coupon_id']);
        });

        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('subtotal_coins')->nullable()->after('total_coins');
            $table->unsignedBigInteger('discount_coins')->default(0)->after('subtotal_coins');
            $table->foreignUuid('store_coupon_id')->nullable()->after('discount_coins')
                ->constrained('store_coupons')->nullOnDelete();
            $table->string('coupon_code', 32)->nullable()->after('store_coupon_id');
        });

        // Backfill existing orders.
        DB::table('shop_orders')->whereNull('subtotal_coins')->update([
            'subtotal_coins' => DB::raw('total_coins'),
            'discount_coins' => 0,
        ]);

        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->index(['seller_id', 'payment_status', 'created_at'], 'shop_orders_seller_analytics_idx');
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->dropIndex('shop_orders_seller_analytics_idx');
            $table->dropConstrainedForeignId('store_coupon_id');
            $table->dropColumn(['subtotal_coins', 'discount_coins', 'coupon_code']);
        });

        Schema::dropIfExists('store_coupon_redemptions');
        Schema::dropIfExists('store_coupons');
    }
};

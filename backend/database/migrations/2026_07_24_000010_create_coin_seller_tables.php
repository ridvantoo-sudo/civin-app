<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_sellers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('display_name', 150);
            $table->string('contact_info', 255)->nullable();
            $table->text('application_message')->nullable();
            $table->string('status', 24)->index();
            $table->decimal('commission_rate', 5, 2)->default(5);
            $table->unsignedBigInteger('inventory_balance')->default(0);
            $table->unsignedBigInteger('total_sold')->default(0);
            $table->unsignedBigInteger('total_commission')->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('coin_seller_inventory_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('coin_seller_id')->constrained('coin_sellers')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 32);
            $table->bigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['coin_seller_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('coin_recharge_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('coin_seller_id')->constrained('coin_sellers')->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->unsignedBigInteger('coins');
            $table->string('status', 24)->index();
            $table->foreignUuid('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['coin_seller_id', 'status', 'created_at']);
        });

        Schema::create('coin_seller_sales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('coin_seller_id')->constrained('coin_sellers')->cascadeOnDelete();
            $table->foreignUuid('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('recharge_code_id')->nullable()->constrained('coin_recharge_codes')->nullOnDelete();
            $table->string('type', 24);
            $table->unsignedBigInteger('coins');
            $table->unsignedBigInteger('price_amount')->default(0);
            $table->string('currency', 8)->default('USD');
            $table->decimal('commission_rate', 5, 2);
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('seller_net_coins');
            $table->string('status', 24)->index();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['coin_seller_id', 'created_at']);
            $table->index(['buyer_id', 'created_at']);
            $table->index(['type', 'status']);
        });

        Schema::create('coin_seller_commissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('coin_seller_id')->constrained('coin_sellers')->cascadeOnDelete();
            $table->foreignUuid('sale_id')->unique()->constrained('coin_seller_sales')->cascadeOnDelete();
            $table->unsignedBigInteger('gross_coins');
            $table->decimal('commission_rate', 5, 2);
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('seller_net_coins');
            $table->string('currency', 16)->default('coins');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['coin_seller_id', 'created_at']);
        });

        Schema::create('coin_seller_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('coin_seller_id')->constrained('coin_sellers')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 48);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['coin_seller_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_seller_audit_logs');
        Schema::dropIfExists('coin_seller_commissions');
        Schema::dropIfExists('coin_seller_sales');
        Schema::dropIfExists('coin_recharge_codes');
        Schema::dropIfExists('coin_seller_inventory_logs');
        Schema::dropIfExists('coin_sellers');
    }
};

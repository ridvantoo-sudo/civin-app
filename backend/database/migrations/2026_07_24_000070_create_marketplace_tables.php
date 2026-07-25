<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 32)->index();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('icon_url', 2048)->nullable();
            $table->string('asset_url', 2048)->nullable();
            $table->unsignedBigInteger('coin_price');
            $table->unsignedInteger('duration_days')->nullable();
            $table->boolean('is_limited')->default(false);
            $table->unsignedInteger('stock_total')->nullable();
            $table->unsignedInteger('stock_remaining')->nullable();
            $table->string('special_value', 64)->nullable()->unique();
            $table->string('membership_required', 16)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['type', 'status', 'sort_order']);
            $table->index(['is_limited', 'status']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('market_purchases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('market_item_id')->constrained('market_items')->restrictOnDelete();
            $table->unsignedBigInteger('coins');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['market_item_id', 'created_at']);
        });

        Schema::create('user_market_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('market_item_id')->constrained('market_items')->restrictOnDelete();
            $table->foreignUuid('purchase_id')->nullable()->constrained('market_purchases')->nullOnDelete();
            $table->enum('status', ['owned', 'expired'])->default('owned')->index();
            $table->boolean('is_equipped')->default(false);
            $table->timestamp('acquired_at');
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'is_equipped']);
            $table->index(['market_item_id', 'status']);
            $table->index(['expires_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_market_items');
        Schema::dropIfExists('market_purchases');
        Schema::dropIfExists('market_items');
    }
};

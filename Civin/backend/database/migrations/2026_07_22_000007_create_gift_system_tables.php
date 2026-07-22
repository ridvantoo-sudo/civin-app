<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('coin_balance')->default(0)->after('status');
            $table->unsignedBigInteger('earning_balance')->default(0)->after('coin_balance');
        });

        Schema::create('gift_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('icon', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->index(['status', 'sort_order']);
        });

        Schema::create('gifts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('gift_categories')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('icon', 2048)->nullable();
            $table->string('animation_url', 2048)->nullable();
            $table->unsignedBigInteger('coin_price');
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->index(['category_id', 'status']);
            $table->index(['status', 'coin_price']);
        });

        Schema::create('gift_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignUuid('gift_id')->constrained('gifts')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('coins');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['sender_id', 'created_at']);
            $table->index(['receiver_id', 'created_at']);
            $table->index(['room_id', 'created_at']);
            $table->index(['gift_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_transactions');
        Schema::dropIfExists('gifts');
        Schema::dropIfExists('gift_categories');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['coin_balance', 'earning_balance']);
        });
    }
};

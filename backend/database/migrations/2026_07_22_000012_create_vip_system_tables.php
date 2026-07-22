<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vip_levels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->unsignedInteger('level')->unique();
            $table->unsignedBigInteger('coin_price');
            $table->unsignedInteger('duration_days');
            $table->string('badge', 2048)->nullable();
            $table->string('profile_frame', 2048)->nullable();
            $table->string('chat_effect', 2048)->nullable();
            $table->string('entrance_animation', 2048)->nullable();
            $table->boolean('exclusive_gifts')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->index(['status', 'sort_order']);
            $table->index(['status', 'level']);
        });

        Schema::create('user_vips', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('vip_level_id')->constrained('vip_levels')->restrictOnDelete();
            $table->string('status', 24)->index();
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });

        Schema::create('vip_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('vip_level_id')->constrained('vip_levels')->restrictOnDelete();
            $table->foreignUuid('user_vip_id')->nullable()->constrained('user_vips')->nullOnDelete();
            $table->string('type', 32);
            $table->unsignedBigInteger('coins');
            $table->unsignedInteger('from_level')->nullable();
            $table->unsignedInteger('to_level');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['vip_level_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vip_transactions');
        Schema::dropIfExists('user_vips');
        Schema::dropIfExists('vip_levels');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lucky_wheel_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('spin_cost_coins')->default(100);
            $table->unsignedInteger('daily_free_spins')->default(0);
            $table->unsignedInteger('max_spins_per_day')->nullable();
            $table->unsignedInteger('cooldown_seconds')->default(0);
            $table->boolean('jackpot_enabled')->default(false);
            $table->unsignedInteger('jackpot_coins')->default(0);
            $table->string('status', 16)->default('active')->index();
            $table->boolean('is_default')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'is_default']);
        });

        Schema::create('lucky_wheel_sectors', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('config_id')->constrained('lucky_wheel_configs')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('label', 64);
            $table->string('reward_type', 24)->default('coins')->index();
            $table->unsignedInteger('reward_amount')->default(0);
            $table->string('reward_reference', 64)->nullable();
            $table->unsignedInteger('weight')->default(1);
            $table->string('color', 16)->nullable();
            $table->string('icon', 2048)->nullable();
            $table->boolean('is_jackpot')->default(false)->index();
            $table->boolean('requires_fulfillment')->default(false);
            $table->string('status', 16)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['config_id', 'position']);
            $table->index(['config_id', 'status']);
        });

        Schema::create('lucky_wheel_spins', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('lucky_wheel_configs')->nullOnDelete();
            $table->foreignUuid('sector_id')->nullable()->constrained('lucky_wheel_sectors')->nullOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignUuid('play_id')->nullable()->constrained('game_plays')->nullOnDelete();
            $table->uuid('reward_id')->nullable();
            $table->uuid('wallet_transaction_id')->nullable();
            $table->string('idempotency_key', 64);
            $table->string('status', 24)->default('settled')->index();
            $table->unsignedInteger('cost_coins')->default(0);
            $table->string('reward_type', 24)->default('none')->index();
            $table->unsignedInteger('reward_amount')->default(0);
            $table->string('reward_currency', 16)->nullable();
            $table->string('reward_reference', 64)->nullable();
            $table->boolean('is_jackpot')->default(false)->index();
            $table->unsignedTinyInteger('sector_position')->nullable();
            $table->string('sector_label', 64)->nullable();
            $table->unsignedInteger('config_version')->default(1);
            $table->string('server_seed', 64)->nullable();
            $table->unsignedInteger('roll')->nullable();
            $table->unsignedInteger('total_weight')->nullable();
            $table->json('outcome')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'lucky_wheel_spins_idempotency_unique');
            $table->index(['user_id', 'created_at']);
            $table->index(['config_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lucky_wheel_spins');
        Schema::dropIfExists('lucky_wheel_sectors');
        Schema::dropIfExists('lucky_wheel_configs');
    }
};

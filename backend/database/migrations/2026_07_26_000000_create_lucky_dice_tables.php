<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lucky_dice_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('min_bet_coins')->default(50);
            $table->unsignedInteger('max_bet_coins')->default(10000);
            $table->unsignedInteger('default_bet_coins')->default(100);
            $table->json('chip_amounts')->nullable();
            $table->unsignedInteger('max_plays_per_day')->nullable();
            $table->unsignedInteger('cooldown_seconds')->default(0);
            $table->unsignedInteger('countdown_seconds')->default(5);
            $table->decimal('multiplier_big_small', 8, 2)->default(2);
            $table->decimal('multiplier_odd_even', 8, 2)->default(2);
            $table->decimal('multiplier_triple', 8, 2)->default(30);
            $table->json('exact_total_multipliers')->nullable();
            $table->boolean('jackpot_enabled')->default(false);
            $table->unsignedInteger('jackpot_coins')->default(0);
            $table->boolean('jackpot_on_triple')->default(true);
            $table->json('modes_enabled')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->boolean('is_default')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'is_default']);
        });

        Schema::create('lucky_dice_bets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('lucky_dice_configs')->nullOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignUuid('play_id')->nullable()->constrained('game_plays')->nullOnDelete();
            $table->uuid('reward_id')->nullable();
            $table->uuid('wallet_transaction_id')->nullable();
            $table->string('session_id', 64)->nullable();
            $table->string('idempotency_key', 64);
            $table->string('bet_mode', 24)->index();
            $table->string('prediction', 32);
            $table->unsignedInteger('bet_amount')->default(0);
            $table->string('status', 24)->default('pending')->index();
            $table->string('reward_type', 24)->default('none')->index();
            $table->unsignedInteger('reward_amount')->default(0);
            $table->string('reward_currency', 16)->nullable();
            $table->string('reward_reference', 64)->nullable();
            $table->boolean('is_jackpot')->default(false)->index();
            $table->boolean('is_win')->default(false)->index();
            $table->decimal('multiplier', 8, 2)->nullable();
            $table->uuid('result_id')->nullable()->index();
            $table->unsignedInteger('config_version')->default(1);
            $table->string('server_seed', 64)->nullable();
            $table->json('outcome')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'lucky_dice_bets_idempotency_unique');
            $table->index(['user_id', 'created_at']);
            $table->index(['config_id', 'created_at']);
            $table->index(['bet_mode', 'created_at']);
        });

        Schema::create('lucky_dice_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('bet_id')->constrained('lucky_dice_bets')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_id', 64)->nullable();
            $table->unsignedTinyInteger('dice_1');
            $table->unsignedTinyInteger('dice_2');
            $table->unsignedTinyInteger('dice_3');
            $table->unsignedTinyInteger('total');
            $table->boolean('is_triple')->default(false)->index();
            $table->string('result', 16)->index();
            $table->string('reward_type', 24)->default('none');
            $table->unsignedInteger('reward_amount')->default(0);
            $table->string('status', 24)->default('settled')->index();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->unique('bet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lucky_dice_results');
        Schema::dropIfExists('lucky_dice_bets');
        Schema::dropIfExists('lucky_dice_configs');
    }
};

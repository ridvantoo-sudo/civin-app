<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_machine_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('min_bet_coins')->default(10);
            $table->unsignedInteger('max_bet_coins')->default(10000);
            $table->unsignedInteger('default_bet_coins')->default(50);
            $table->json('chip_amounts')->nullable();
            $table->json('modes_enabled')->nullable();
            $table->decimal('rtp_percent', 5, 2)->default(96.00);
            $table->decimal('house_edge_percent', 5, 2)->default(4.00);
            $table->unsignedTinyInteger('classic_columns')->default(3);
            $table->unsignedTinyInteger('classic_rows')->default(3);
            $table->unsignedTinyInteger('premium_columns')->default(5);
            $table->unsignedTinyInteger('premium_rows')->default(3);
            $table->unsignedInteger('max_spins_per_day')->nullable();
            $table->unsignedInteger('cooldown_seconds')->default(0);
            $table->unsignedInteger('daily_free_spins')->default(0);
            $table->unsignedInteger('max_auto_spins')->default(50);
            $table->unsignedTinyInteger('max_spin_count')->default(10);
            $table->unsignedInteger('free_spin_award_on_scatters')->default(3);
            $table->unsignedInteger('free_spins_granted')->default(10);
            $table->decimal('progressive_contribution_percent', 5, 2)->default(1.00);
            $table->boolean('jackpot_enabled')->default(true);
            $table->boolean('bonus_enabled')->default(true);
            $table->unsignedInteger('bonus_trigger_symbols')->default(3);
            $table->decimal('vip_reward_multiplier', 5, 2)->default(1.25);
            $table->json('bonus_probabilities')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->boolean('is_default')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'is_default']);
        });

        Schema::create('slot_machine_symbols', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('config_id')->constrained('slot_machine_configs')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('label', 64);
            $table->string('icon', 2048)->nullable();
            $table->string('color', 16)->nullable();
            $table->unsignedInteger('weight')->default(1);
            $table->unsignedInteger('payout_3')->default(0);
            $table->unsignedInteger('payout_4')->default(0);
            $table->unsignedInteger('payout_5')->default(0);
            $table->boolean('is_wild')->default(false)->index();
            $table->boolean('is_scatter')->default(false)->index();
            $table->boolean('is_bonus')->default(false)->index();
            $table->boolean('is_jackpot')->default(false)->index();
            $table->boolean('is_multiplier')->default(false)->index();
            $table->unsignedInteger('multiplier_value')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 16)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['config_id', 'code']);
            $table->index(['config_id', 'status']);
        });

        Schema::create('slot_machine_paylines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('config_id')->constrained('slot_machine_configs')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('label', 64);
            $table->string('layout', 16)->default('classic')->index();
            $table->json('positions');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 16)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['config_id', 'code']);
            $table->index(['config_id', 'layout', 'status']);
        });

        Schema::create('slot_machine_jackpots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('config_id')->constrained('slot_machine_configs')->cascadeOnDelete();
            $table->string('type', 32)->index();
            $table->string('name', 120);
            $table->unsignedBigInteger('pool_coins')->default(0);
            $table->unsignedBigInteger('seed_coins')->default(0);
            $table->unsignedBigInteger('min_pool_coins')->default(0);
            $table->decimal('contribution_percent', 5, 2)->default(0);
            $table->boolean('requires_vip')->default(false);
            $table->string('room_id', 64)->nullable()->index();
            $table->date('daily_date')->nullable()->index();
            $table->string('status', 16)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('last_won_at')->nullable();
            $table->uuid('last_winner_user_id')->nullable();
            $table->timestamps();
            $table->unique(['config_id', 'type', 'room_id', 'daily_date'], 'slot_jackpots_unique');
        });

        Schema::create('slot_machine_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('slot_machine_configs')->nullOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->string('client_session_id', 64)->nullable()->index();
            $table->string('game_mode', 32)->default('classic');
            $table->unsignedInteger('bet_amount')->default(0);
            $table->unsignedInteger('auto_spin_remaining')->default(0);
            $table->unsignedInteger('free_spins_remaining')->default(0);
            $table->unsignedInteger('bonus_spins_remaining')->default(0);
            $table->string('status', 16)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('last_spin_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('slot_machine_spins', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('slot_machine_configs')->nullOnDelete();
            $table->foreignUuid('session_id')->nullable()->constrained('slot_machine_sessions')->nullOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignUuid('play_id')->nullable()->constrained('game_plays')->nullOnDelete();
            $table->uuid('reward_id')->nullable();
            $table->uuid('wallet_transaction_id')->nullable();
            $table->string('idempotency_key', 64);
            $table->string('status', 24)->default('settled')->index();
            $table->string('game_mode', 32)->default('classic')->index();
            $table->unsignedInteger('bet_amount')->default(0);
            $table->unsignedInteger('spin_count')->default(1);
            $table->boolean('auto_spin')->default(false);
            $table->unsignedInteger('cost_coins')->default(0);
            $table->unsignedInteger('reward_coins')->default(0);
            $table->unsignedInteger('reward_diamonds')->default(0);
            $table->unsignedInteger('free_spins_awarded')->default(0);
            $table->boolean('is_win')->default(false)->index();
            $table->boolean('is_jackpot')->default(false)->index();
            $table->boolean('is_bonus')->default(false)->index();
            $table->boolean('is_free_spin')->default(false)->index();
            $table->string('jackpot_type', 32)->nullable();
            $table->unsignedBigInteger('jackpot_coins')->default(0);
            $table->unsignedInteger('config_version')->default(1);
            $table->string('server_seed', 64)->nullable();
            $table->json('rolls')->nullable();
            $table->json('grid')->nullable();
            $table->json('outcome')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'slot_machine_spins_idempotency_unique');
            $table->index(['user_id', 'created_at']);
            $table->index(['config_id', 'created_at']);
            $table->index(['is_jackpot', 'created_at']);
        });

        Schema::create('slot_machine_rewards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('spin_id')->constrained('slot_machine_spins')->cascadeOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('slot_machine_configs')->nullOnDelete();
            $table->string('reward_type', 32)->default('coins')->index();
            $table->unsignedInteger('amount')->default(0);
            $table->string('currency', 16)->nullable();
            $table->string('reference', 64)->nullable();
            $table->boolean('is_jackpot')->default(false);
            $table->boolean('is_bonus')->default(false);
            $table->boolean('is_combo')->default(false);
            $table->boolean('is_daily')->default(false);
            $table->boolean('is_vip')->default(false);
            $table->string('status', 24)->default('granted')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('slot_machine_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('spin_id')->nullable()->constrained('slot_machine_spins')->nullOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('slot_machine_configs')->nullOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignUuid('play_id')->nullable()->constrained('game_plays')->nullOnDelete();
            $table->string('game_mode', 32)->nullable();
            $table->string('result', 16)->default('lost');
            $table->unsignedInteger('bet_coins')->default(0);
            $table->unsignedInteger('reward_coins')->default(0);
            $table->unsignedInteger('reward_diamonds')->default(0);
            $table->boolean('is_jackpot')->default(false);
            $table->boolean('is_bonus')->default(false);
            $table->json('snapshot')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('slot_machine_statistics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('slot_machine_configs')->nullOnDelete();
            $table->unsignedBigInteger('games_played')->default(0);
            $table->unsignedBigInteger('coins_wagered')->default(0);
            $table->unsignedBigInteger('coins_won')->default(0);
            $table->unsignedBigInteger('diamonds_won')->default(0);
            $table->unsignedBigInteger('jackpots')->default(0);
            $table->unsignedBigInteger('bonus_wins')->default(0);
            $table->unsignedBigInteger('free_spins_played')->default(0);
            $table->unsignedBigInteger('highest_win')->default(0);
            $table->decimal('rtp_percent', 8, 4)->default(0);
            $table->decimal('average_win', 12, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'config_id'], 'slot_machine_statistics_user_config_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_machine_statistics');
        Schema::dropIfExists('slot_machine_histories');
        Schema::dropIfExists('slot_machine_rewards');
        Schema::dropIfExists('slot_machine_spins');
        Schema::dropIfExists('slot_machine_sessions');
        Schema::dropIfExists('slot_machine_jackpots');
        Schema::dropIfExists('slot_machine_paylines');
        Schema::dropIfExists('slot_machine_symbols');
        Schema::dropIfExists('slot_machine_configs');
    }
};

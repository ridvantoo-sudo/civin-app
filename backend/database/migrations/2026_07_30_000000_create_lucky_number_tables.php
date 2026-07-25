<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lucky_number_configs', function (Blueprint $table): void {
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
            $table->json('number_ranges')->nullable();
            $table->json('mode_ranges')->nullable();
            $table->json('reward_multipliers')->nullable();
            $table->unsignedTinyInteger('default_min_number')->default(1);
            $table->unsignedInteger('default_max_number')->default(10);
            $table->unsignedTinyInteger('max_picks')->default(5);
            $table->unsignedTinyInteger('draw_count')->default(1);
            $table->decimal('rtp_percent', 5, 2)->default(96.00);
            $table->decimal('house_edge_percent', 5, 2)->default(4.00);
            $table->unsignedInteger('max_tickets_per_day')->nullable();
            $table->unsignedInteger('cooldown_seconds')->default(0);
            $table->decimal('progressive_contribution_percent', 5, 2)->default(1.00);
            $table->boolean('jackpot_enabled')->default(true);
            $table->boolean('bonus_enabled')->default(true);
            $table->decimal('vip_reward_multiplier', 5, 2)->default(1.50);
            $table->json('bonus_probabilities')->nullable();
            $table->unsignedInteger('draw_interval_seconds')->default(300);
            $table->string('status', 16)->default('active')->index();
            $table->boolean('is_default')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'is_default']);
        });

        Schema::create('lucky_number_jackpots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('config_id')->constrained('lucky_number_configs')->cascadeOnDelete();
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
            $table->unique(['config_id', 'type', 'room_id', 'daily_date'], 'lucky_number_jackpots_unique');
        });

        Schema::create('lucky_number_draws', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('config_id')->constrained('lucky_number_configs')->cascadeOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->string('draw_type', 32)->default('instant')->index();
            $table->string('game_mode', 32)->nullable()->index();
            $table->unsignedInteger('min_number')->default(1);
            $table->unsignedInteger('max_number')->default(10);
            $table->unsignedTinyInteger('draw_count')->default(1);
            $table->string('status', 24)->default('pending')->index();
            $table->string('server_seed', 64)->nullable();
            $table->json('winning_numbers')->nullable();
            $table->json('audit_log')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->uuid('triggered_by_user_id')->nullable();
            $table->timestamps();
            $table->index(['config_id', 'status', 'scheduled_at']);
        });

        Schema::create('lucky_number_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('lucky_number_configs')->nullOnDelete();
            $table->foreignUuid('draw_id')->nullable()->constrained('lucky_number_draws')->nullOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignUuid('play_id')->nullable()->constrained('game_plays')->nullOnDelete();
            $table->uuid('reward_id')->nullable();
            $table->uuid('wallet_transaction_id')->nullable();
            $table->string('idempotency_key', 64);
            $table->string('status', 24)->default('settled')->index();
            $table->string('game_mode', 32)->default('single_number')->index();
            $table->unsignedInteger('min_number')->default(1);
            $table->unsignedInteger('max_number')->default(10);
            $table->json('picked_numbers')->nullable();
            $table->boolean('auto_pick')->default(false);
            $table->boolean('repeat_ticket')->default(false);
            $table->unsignedInteger('bet_amount')->default(0);
            $table->unsignedInteger('cost_coins')->default(0);
            $table->unsignedInteger('reward_coins')->default(0);
            $table->unsignedInteger('reward_diamonds')->default(0);
            $table->boolean('is_win')->default(false)->index();
            $table->boolean('is_jackpot')->default(false)->index();
            $table->boolean('is_bonus')->default(false)->index();
            $table->string('jackpot_type', 32)->nullable();
            $table->unsignedBigInteger('jackpot_coins')->default(0);
            $table->unsignedInteger('config_version')->default(1);
            $table->string('server_seed', 64)->nullable();
            $table->json('outcome')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'lucky_number_tickets_idempotency_unique');
            $table->index(['user_id', 'created_at']);
            $table->index(['config_id', 'created_at']);
            $table->index(['draw_id', 'status']);
            $table->index(['is_jackpot', 'created_at']);
        });

        Schema::create('lucky_number_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('lucky_number_tickets')->cascadeOnDelete();
            $table->foreignUuid('draw_id')->nullable()->constrained('lucky_number_draws')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('lucky_number_configs')->nullOnDelete();
            $table->json('picked_numbers')->nullable();
            $table->json('winning_numbers')->nullable();
            $table->unsignedTinyInteger('match_count')->default(0);
            $table->decimal('multiplier', 10, 4)->default(0);
            $table->unsignedInteger('reward_coins')->default(0);
            $table->boolean('is_win')->default(false);
            $table->boolean('is_jackpot')->default(false);
            $table->json('audit')->nullable();
            $table->timestamps();
            $table->unique('ticket_id');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('lucky_number_rewards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('lucky_number_tickets')->cascadeOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('lucky_number_configs')->nullOnDelete();
            $table->string('reward_type', 32)->default('coins')->index();
            $table->unsignedInteger('amount')->default(0);
            $table->string('currency', 16)->nullable();
            $table->string('reference', 64)->nullable();
            $table->boolean('is_jackpot')->default(false);
            $table->boolean('is_bonus')->default(false);
            $table->boolean('is_daily')->default(false);
            $table->boolean('is_vip')->default(false);
            $table->boolean('is_streak')->default(false);
            $table->boolean('is_referral')->default(false);
            $table->boolean('is_mystery')->default(false);
            $table->string('status', 24)->default('granted')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('lucky_number_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->nullable()->constrained('lucky_number_tickets')->nullOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('lucky_number_configs')->nullOnDelete();
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

        Schema::create('lucky_number_statistics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('lucky_number_configs')->nullOnDelete();
            $table->unsignedBigInteger('games_played')->default(0);
            $table->unsignedBigInteger('tickets_purchased')->default(0);
            $table->unsignedBigInteger('coins_wagered')->default(0);
            $table->unsignedBigInteger('coins_won')->default(0);
            $table->unsignedBigInteger('diamonds_won')->default(0);
            $table->unsignedBigInteger('jackpots_won')->default(0);
            $table->unsignedBigInteger('highest_win')->default(0);
            $table->decimal('average_win', 12, 2)->default(0);
            $table->decimal('win_percentage', 8, 4)->default(0);
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('best_streak')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'config_id'], 'lucky_number_statistics_user_config_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lucky_number_statistics');
        Schema::dropIfExists('lucky_number_histories');
        Schema::dropIfExists('lucky_number_rewards');
        Schema::dropIfExists('lucky_number_results');
        Schema::dropIfExists('lucky_number_tickets');
        Schema::dropIfExists('lucky_number_draws');
        Schema::dropIfExists('lucky_number_jackpots');
        Schema::dropIfExists('lucky_number_configs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('icon', 2048)->nullable();
            $table->string('category', 32)->default('solo')->index();
            $table->unsignedInteger('min_bet_coins')->default(10);
            $table->unsignedInteger('max_bet_coins')->default(10000);
            $table->unsignedInteger('house_edge_bps')->default(500);
            $table->boolean('supports_multiplayer')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->index(['status', 'sort_order']);
        });

        Schema::create('game_plays', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('match_id')->nullable()->index();
            $table->unsignedInteger('bet_coins')->default(0);
            $table->unsignedInteger('bet_diamonds')->default(0);
            $table->unsignedInteger('reward_coins')->default(0);
            $table->unsignedInteger('reward_diamonds')->default(0);
            $table->string('result', 16)->default('lost')->index();
            $table->string('status', 16)->default('completed')->index();
            $table->json('input')->nullable();
            $table->json('outcome')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['game_id', 'created_at']);
            $table->index(['user_id', 'game_id', 'created_at']);
        });

        Schema::create('game_matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignUuid('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('max_players')->default(2);
            $table->unsignedInteger('stake_coins')->default(0);
            $table->string('status', 24)->default('waiting')->index();
            $table->json('state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['game_id', 'status']);
        });

        Schema::create('game_match_players', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('match_id')->constrained('game_matches')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('seat')->default(0);
            $table->string('status', 24)->default('joined')->index();
            $table->integer('score')->default(0);
            $table->json('state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['match_id', 'user_id']);
            $table->unique(['match_id', 'seat']);
        });

        Schema::create('game_leaderboard_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->cascadeOnDelete();
            $table->string('period', 16)->index();
            $table->string('period_key', 32);
            $table->unsignedInteger('plays')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('coins_wagered')->default(0);
            $table->integer('coins_net')->default(0);
            $table->unsignedInteger('score')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'game_id', 'period', 'period_key'], 'game_lb_unique');
            $table->index(['period', 'period_key', 'score']);
            $table->index(['game_id', 'period', 'period_key', 'score']);
        });

        Schema::create('game_analytics_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->string('event', 64)->index();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['game_id', 'event', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('quiz_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('question', 500);
            $table->json('options');
            $table->unsignedTinyInteger('correct_index');
            $table->string('difficulty', 16)->default('easy')->index();
            $table->unsignedInteger('reward_coins')->default(20);
            $table->unsignedInteger('bet_coins')->default(10);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
        });

        Schema::create('lottery_rounds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->unsignedInteger('ticket_price')->default(50);
            $table->unsignedTinyInteger('max_number')->default(49);
            $table->unsignedTinyInteger('picks')->default(6);
            $table->json('winning_numbers')->nullable();
            $table->string('status', 16)->default('open')->index();
            $table->timestamp('closes_at')->nullable();
            $table->timestamp('drawn_at')->nullable();
            $table->timestamps();
            $table->index(['game_id', 'status']);
        });

        Schema::create('lottery_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('round_id')->constrained('lottery_rounds')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('numbers');
            $table->unsignedInteger('coins_paid')->default(0);
            $table->unsignedInteger('reward_coins')->default(0);
            $table->string('status', 16)->default('pending')->index();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['round_id', 'user_id']);
        });

        Schema::create('red_envelopes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('total_coins');
            $table->unsignedInteger('remaining_coins');
            $table->unsignedTinyInteger('total_slots');
            $table->unsignedTinyInteger('remaining_slots');
            $table->string('message', 200)->nullable();
            $table->string('status', 16)->default('open')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['sender_id', 'created_at']);
        });

        Schema::create('red_envelope_claims', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('envelope_id')->constrained('red_envelopes')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('coins');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['envelope_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('red_envelope_claims');
        Schema::dropIfExists('red_envelopes');
        Schema::dropIfExists('lottery_tickets');
        Schema::dropIfExists('lottery_rounds');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('game_analytics_events');
        Schema::dropIfExists('game_leaderboard_entries');
        Schema::dropIfExists('game_match_players');
        Schema::dropIfExists('game_matches');
        Schema::dropIfExists('game_plays');
        Schema::dropIfExists('games');
    }
};

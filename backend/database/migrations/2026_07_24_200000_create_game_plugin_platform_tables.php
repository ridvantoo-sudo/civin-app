<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_plugins', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('version', 32)->default('1.0.0');
            $table->boolean('enabled')->default(true)->index();
            $table->json('config')->nullable();
            $table->json('reward_config')->nullable();
            $table->unsignedInteger('coin_limit_per_day')->nullable();
            $table->unsignedInteger('coin_limit_per_play')->nullable();
            $table->timestamps();
        });

        Schema::create('game_rooms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignUuid('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 16)->nullable()->unique();
            $table->string('status', 24)->default('open')->index();
            $table->unsignedTinyInteger('max_players')->default(4);
            $table->unsignedInteger('stake_coins')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['game_id', 'status']);
        });

        Schema::create('game_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('game_rooms')->nullOnDelete();
            $table->foreignUuid('match_id')->nullable()->constrained('game_matches')->nullOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->json('state')->nullable();
            $table->timestamp('countdown_ends_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['game_id', 'status']);
        });

        Schema::create('game_players', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('seat')->default(0);
            $table->string('status', 24)->default('joined')->index();
            $table->integer('score')->default(0);
            $table->json('state')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'user_id']);
            $table->unique(['session_id', 'seat']);
        });

        Schema::create('game_rounds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->unsignedInteger('round_number')->default(1);
            $table->string('status', 24)->default('pending')->index();
            $table->json('input')->nullable();
            $table->json('outcome')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'round_number']);
        });

        Schema::create('game_rewards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignUuid('play_id')->nullable()->constrained('game_plays')->nullOnDelete();
            $table->foreignUuid('session_id')->nullable()->constrained('game_sessions')->nullOnDelete();
            $table->unsignedInteger('coins')->default(0);
            $table->unsignedInteger('diamonds')->default(0);
            $table->string('status', 24)->default('granted')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('game_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignUuid('play_id')->nullable()->constrained('game_plays')->nullOnDelete();
            $table->string('type', 32)->index();
            $table->integer('amount');
            $table->string('currency', 16)->default('coins');
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('game_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignUuid('play_id')->nullable()->constrained('game_plays')->nullOnDelete();
            $table->string('result', 16)->default('lost');
            $table->unsignedInteger('bet_coins')->default(0);
            $table->unsignedInteger('reward_coins')->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['game_id', 'created_at']);
        });

        Schema::create('game_leaderboards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->cascadeOnDelete();
            $table->string('period', 16)->index();
            $table->string('period_key', 32);
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('plays')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'game_id', 'period', 'period_key'], 'game_leaderboards_unique');
            $table->index(['period', 'period_key', 'score']);
        });

        Schema::create('game_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->string('level', 16)->default('info')->index();
            $table->string('event', 64)->index();
            $table->string('message', 500)->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['game_id', 'event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_logs');
        Schema::dropIfExists('game_leaderboards');
        Schema::dropIfExists('game_histories');
        Schema::dropIfExists('game_transactions');
        Schema::dropIfExists('game_rewards');
        Schema::dropIfExists('game_rounds');
        Schema::dropIfExists('game_players');
        Schema::dropIfExists('game_sessions');
        Schema::dropIfExists('game_rooms');
        Schema::dropIfExists('game_plugins');
    }
};

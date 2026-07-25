<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasure_box_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('min_coins_amount')->default(100);
            $table->unsignedInteger('max_coins_amount')->default(1000000);
            $table->unsignedInteger('min_diamonds_amount')->default(10);
            $table->unsignedInteger('max_diamonds_amount')->default(100000);
            $table->unsignedInteger('max_winners')->default(100);
            $table->unsignedInteger('min_countdown_seconds')->default(5);
            $table->unsignedInteger('max_countdown_seconds')->default(3600);
            $table->unsignedInteger('default_countdown_seconds')->default(30);
            $table->unsignedInteger('min_claim_duration_seconds')->default(30);
            $table->unsignedInteger('max_claim_duration_seconds')->default(86400);
            $table->unsignedInteger('default_claim_duration_seconds')->default(300);
            $table->unsignedInteger('max_created_per_day')->nullable();
            $table->boolean('refund_unclaimed')->default(true);
            $table->json('allowed_reward_types')->nullable();
            $table->json('allowed_types')->nullable();
            $table->json('allowed_visibilities')->nullable();
            $table->json('allowed_eligibilities')->nullable();
            $table->json('allowed_room_types')->nullable();
            $table->json('auto_rules')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->string('status', 16)->default('active')->index();
            $table->boolean('is_default')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'is_default']);
        });

        Schema::create('treasure_boxes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('treasure_box_configs')->nullOnDelete();
            $table->foreignUuid('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 24)->default('instant')->index();
            $table->string('status', 24)->default('open')->index();
            $table->string('reward_type', 32)->default('coins')->index();
            $table->unsignedInteger('total_amount')->default(0);
            $table->unsignedInteger('remaining_amount')->default(0);
            $table->unsignedInteger('winner_count')->default(1);
            $table->unsignedInteger('participant_count')->default(0);
            $table->uuid('room_id')->nullable();
            $table->string('room_type', 24)->nullable()->index();
            $table->string('visibility', 24)->default('public')->index();
            $table->string('eligibility_scope', 32)->default('everyone')->index();
            $table->json('whitelist_user_ids')->nullable();
            $table->unsignedInteger('min_watch_time_seconds')->nullable();
            $table->unsignedInteger('min_user_level')->nullable();
            $table->unsignedInteger('min_gift_value')->nullable();
            $table->unsignedInteger('countdown_seconds')->default(30);
            $table->unsignedInteger('claim_duration_seconds')->default(300);
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('countdown_started_at')->nullable();
            $table->timestamp('opens_at')->nullable()->index();
            $table->timestamp('draw_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('auto_start')->default(true);
            $table->string('message', 200)->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->foreignUuid('game_play_id')->nullable()->constrained('game_plays')->nullOnDelete();
            $table->uuid('wallet_transaction_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['creator_id', 'idempotency_key'], 'treasure_boxes_idempotency_unique');
            $table->index(['room_type', 'room_id', 'status'], 'treasure_boxes_room_status_index');
            $table->index(['status', 'draw_at'], 'treasure_boxes_status_draw_index');
            $table->index(['status', 'expires_at'], 'treasure_boxes_status_expires_index');
            $table->index(['creator_id', 'created_at'], 'treasure_boxes_creator_created_index');
        });

        Schema::create('treasure_box_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('treasure_box_id')->constrained('treasure_boxes')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->string('idempotency_key', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['treasure_box_id', 'user_id'], 'treasure_box_participants_unique');
            $table->unique(['user_id', 'idempotency_key'], 'treasure_box_participants_idempotency_unique');
            $table->index(['treasure_box_id', 'joined_at'], 'treasure_box_participants_joined_index');
        });

        Schema::create('treasure_box_rewards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('treasure_box_id')->constrained('treasure_boxes')->cascadeOnDelete();
            $table->string('reward_type', 32);
            $table->unsignedInteger('amount')->default(0);
            $table->string('label', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['treasure_box_id', 'sort_order'], 'treasure_box_rewards_sort_index');
        });

        Schema::create('treasure_box_winners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('treasure_box_id')->constrained('treasure_boxes')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('participant_id')->nullable()->constrained('treasure_box_participants')->nullOnDelete();
            $table->string('reward_type', 32);
            $table->unsignedInteger('reward_amount')->default(0);
            $table->boolean('is_jackpot')->default(false);
            $table->uuid('wallet_transaction_id')->nullable();
            $table->foreignUuid('play_id')->nullable()->constrained('game_plays')->nullOnDelete();
            $table->timestamp('rewarded_at')->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['treasure_box_id', 'user_id'], 'treasure_box_winners_unique');
            $table->unique(['user_id', 'idempotency_key'], 'treasure_box_winners_idempotency_unique');
            $table->index(['treasure_box_id', 'rewarded_at'], 'treasure_box_winners_rewarded_index');
        });

        Schema::create('treasure_box_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('treasure_box_id')->constrained('treasure_boxes')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 64)->index();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['treasure_box_id', 'created_at'], 'treasure_box_histories_box_created_index');
            $table->index(['user_id', 'created_at'], 'treasure_box_histories_user_created_index');
        });

        Schema::create('treasure_box_statistics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope', 24)->default('user')->index();
            $table->unsignedInteger('treasures_created')->default(0);
            $table->unsignedInteger('treasures_joined')->default(0);
            $table->unsignedInteger('treasures_won')->default(0);
            $table->unsignedBigInteger('total_reward_amount')->default(0);
            $table->unsignedBigInteger('total_spent_amount')->default(0);
            $table->unsignedInteger('jackpots_won')->default(0);
            $table->unsignedInteger('average_join_seconds')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('user_id', 'treasure_box_statistics_user_unique');
            $table->index(['scope', 'user_id'], 'treasure_box_statistics_scope_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasure_box_statistics');
        Schema::dropIfExists('treasure_box_histories');
        Schema::dropIfExists('treasure_box_winners');
        Schema::dropIfExists('treasure_box_rewards');
        Schema::dropIfExists('treasure_box_participants');
        Schema::dropIfExists('treasure_boxes');
        Schema::dropIfExists('treasure_box_configs');
    }
};

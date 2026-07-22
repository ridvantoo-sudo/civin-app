<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pk_battles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_a_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignUuid('room_b_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignUuid('host_a_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('host_b_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->index();
            $table->unsignedInteger('duration_seconds');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignUuid('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['room_a_id', 'status']);
            $table->index(['room_b_id', 'status']);
            $table->index(['host_a_id', 'status']);
            $table->index(['host_b_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('pk_scores', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pk_battle_id')->constrained('pk_battles')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('score')->default(0);
            $table->unsignedBigInteger('gift_coins')->default(0);
            $table->timestamp('updated_at');
            $table->unique(['pk_battle_id', 'user_id']);
            $table->index(['pk_battle_id', 'score']);
        });

        Schema::create('pk_rewards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pk_battle_id')->constrained('pk_battles')->cascadeOnDelete();
            $table->foreignUuid('winner_id')->constrained('users')->cascadeOnDelete();
            $table->string('reward_type', 32);
            $table->unsignedBigInteger('amount');
            $table->timestamp('created_at');
            $table->index(['pk_battle_id', 'created_at']);
            $table->index(['winner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pk_rewards');
        Schema::dropIfExists('pk_scores');
        Schema::dropIfExists('pk_battles');
    }
};

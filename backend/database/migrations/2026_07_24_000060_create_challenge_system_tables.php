<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('title', 120);
            $table->string('description', 500)->nullable();
            $table->string('period', 16)->index();
            $table->string('task_type', 32)->index();
            $table->unsignedInteger('target')->default(1);
            $table->unsignedInteger('reward_xp')->default(0);
            $table->unsignedInteger('reward_coins')->default(0);
            $table->unsignedInteger('reward_diamonds')->default(0);
            $table->string('icon', 2048)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['period', 'status', 'sort_order']);
            $table->index(['task_type', 'status']);
        });

        Schema::create('user_challenge_progress', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->string('period_key', 32);
            $table->unsignedInteger('progress')->default(0);
            $table->unsignedInteger('xp_awarded')->nullable();
            $table->unsignedInteger('coins_awarded')->nullable();
            $table->unsignedInteger('diamonds_awarded')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'challenge_id', 'period_key'], 'user_challenge_period_unique');
            $table->index(['user_id', 'period_key']);
            $table->index(['user_id', 'claimed_at']);
            $table->index(['challenge_id', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_challenge_progress');
        Schema::dropIfExists('challenges');
    }
};

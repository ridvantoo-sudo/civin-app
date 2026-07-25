<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_level_progress', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('level')->default(1);
            $table->unsignedBigInteger('xp_total')->default(0);
            $table->unsignedBigInteger('xp_into_level')->default(0);
            $table->timestamps();
            $table->index(['level', 'xp_total']);
        });

        Schema::create('xp_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source', 32);
            $table->unsignedInteger('amount');
            $table->unsignedInteger('level_before');
            $table->unsignedInteger('level_after');
            $table->unsignedBigInteger('xp_total_after');
            $table->string('idempotency_key', 191)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'source', 'created_at']);
            $table->index(['source', 'created_at']);
        });

        Schema::create('daily_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('title', 120);
            $table->string('description', 500)->nullable();
            $table->string('source', 32);
            $table->unsignedInteger('target')->default(1);
            $table->unsignedInteger('xp_reward');
            $table->string('icon', 2048)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['status', 'sort_order']);
        });

        Schema::create('user_daily_task_progress', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('daily_task_id')->constrained('daily_tasks')->cascadeOnDelete();
            $table->date('task_date');
            $table->unsignedInteger('progress')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'daily_task_id', 'task_date']);
            $table->index(['user_id', 'task_date']);
        });

        Schema::create('badges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('icon', 2048)->nullable();
            $table->string('animation_url', 2048)->nullable();
            $table->string('unlock_type', 32);
            $table->unsignedInteger('unlock_value')->default(1);
            $table->unsignedInteger('xp_reward')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['status', 'sort_order']);
            $table->index(['unlock_type', 'unlock_value']);
        });

        Schema::create('user_badges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('badge_id')->constrained('badges')->cascadeOnDelete();
            $table->timestamp('awarded_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'badge_id']);
            $table->index(['user_id', 'awarded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('user_daily_task_progress');
        Schema::dropIfExists('daily_tasks');
        Schema::dropIfExists('xp_transactions');
        Schema::dropIfExists('user_level_progress');
    }
};

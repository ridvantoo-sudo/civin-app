<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couples', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('user_two_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('proposer_id')->constrained('users')->cascadeOnDelete();
            $table->string('display_name', 150)->nullable();
            $table->string('cover_url', 2048)->nullable();
            $table->string('badge_key', 40)->default('bronze');
            $table->string('badge_url', 2048)->nullable();
            $table->unsignedInteger('level')->default(1);
            $table->unsignedBigInteger('xp')->default(0);
            $table->unsignedBigInteger('xp_to_next_level')->default(100);
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('anniversary_at')->nullable()->index();
            $table->unsignedSmallInteger('last_anniversary_bonus_year')->nullable();
            $table->unsignedBigInteger('ranking_score')->default(0);
            $table->unsignedBigInteger('total_gifts_sent')->default(0);
            $table->foreignUuid('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['user_one_id', 'user_two_id']);
            $table->index(['status', 'ranking_score']);
            $table->index(['level', 'xp']);
            $table->index(['user_one_id', 'status']);
            $table->index(['user_two_id', 'status']);
        });

        Schema::create('couple_proposals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('message', 500)->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->foreignUuid('couple_id')->nullable()->constrained('couples')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['from_user_id', 'status']);
            $table->index(['to_user_id', 'status']);
        });

        Schema::create('couple_gifts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('gift_id')->constrained('gifts')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('coins');
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['couple_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
        });

        Schema::create('couple_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 64)->unique();
            $table->string('title', 150);
            $table->string('description', 1000)->nullable();
            $table->string('period', 24)->default('daily');
            $table->unsignedInteger('target_value')->default(1);
            $table->unsignedInteger('reward_xp')->default(10);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('couple_task_progress', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignUuid('task_id')->constrained('couple_tasks')->cascadeOnDelete();
            $table->string('period_key', 32);
            $table->unsignedInteger('progress')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
            $table->unique(['couple_id', 'task_id', 'period_key'], 'couple_task_progress_unique');
            $table->index(['couple_id', 'period_key']);
        });

        $now = now();
        $tasks = [
            ['key' => 'daily_checkin', 'title' => 'Couple Check-in', 'description' => 'Check in together once today.', 'period' => 'daily', 'target_value' => 1, 'reward_xp' => 25],
            ['key' => 'daily_gift', 'title' => 'Send a Gift', 'description' => 'Send 1 couple gift today.', 'period' => 'daily', 'target_value' => 1, 'reward_xp' => 40],
            ['key' => 'weekly_gifts', 'title' => 'Gift Week', 'description' => 'Send 5 couple gifts this week.', 'period' => 'weekly', 'target_value' => 5, 'reward_xp' => 120],
            ['key' => 'weekly_checkins', 'title' => 'Stay Close', 'description' => 'Check in 5 days this week.', 'period' => 'weekly', 'target_value' => 5, 'reward_xp' => 80],
        ];

        foreach ($tasks as $task) {
            DB::table('couple_tasks')->insert([
                'id' => (string) Str::uuid(),
                'key' => $task['key'],
                'title' => $task['title'],
                'description' => $task['description'],
                'period' => $task['period'],
                'target_value' => $task['target_value'],
                'reward_xp' => $task['reward_xp'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('couple_task_progress');
        Schema::dropIfExists('couple_tasks');
        Schema::dropIfExists('couple_gifts');
        Schema::dropIfExists('couple_proposals');
        Schema::dropIfExists('couples');
    }
};

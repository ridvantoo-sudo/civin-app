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
        Schema::create('mentorship_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('target_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 16); // mentor|mentee — role the requester wants to become relative to target
            $table->string('message', 500)->nullable();
            $table->string('status', 24)->default('pending')->index(); // pending|accepted|rejected|cancelled
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['target_id', 'status']);
            $table->index(['requester_id', 'status']);
        });

        Schema::create('mentorships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('mentee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('request_id')->nullable()->constrained('mentorship_requests')->nullOnDelete();
            $table->string('status', 24)->default('active')->index(); // active|ended
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['mentor_id', 'mentee_id']);
            $table->index(['mentor_id', 'status']);
            $table->index(['mentee_id', 'status']);
        });

        Schema::create('mentorship_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('mentorship_id')->constrained('mentorships')->cascadeOnDelete();
            $table->foreignUuid('logged_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->string('notes', 1000)->nullable();
            $table->unsignedBigInteger('mentor_coins')->default(0);
            $table->unsignedBigInteger('mentee_coins')->default(0);
            $table->timestamp('held_at');
            $table->timestamps();

            $table->index(['mentorship_id', 'held_at']);
        });

        Schema::create('achievements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('icon', 2048)->nullable();
            $table->string('category', 40)->default('general')->index();
            $table->string('metric', 40)->index(); // check_ins|referrals|gifts_sent|live_minutes|followers|mentorships|custom
            $table->unsignedBigInteger('target')->default(1);
            $table->unsignedBigInteger('coins_reward')->default(0);
            $table->unsignedBigInteger('diamonds_reward')->default(0);
            $table->unsignedInteger('xp_reward')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('user_achievements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('achievement_id')->constrained('achievements')->cascadeOnDelete();
            $table->unsignedBigInteger('progress')->default(0);
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'achievement_id']);
            $table->index(['user_id', 'unlocked_at']);
        });

        Schema::create('user_check_ins', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('check_in_date');
            $table->unsignedInteger('streak_day')->default(1);
            $table->unsignedBigInteger('coins_awarded')->default(0);
            $table->unsignedBigInteger('diamonds_awarded')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'check_in_date']);
            $table->index(['user_id', 'created_at']);
        });

        $this->seedAchievements();
    }

    private function seedAchievements(): void
    {
        $now = now();
        $rows = [
            ['first_check_in', 'First Check-in', 'Check in for the first time', 'check_in', 'check_ins', 1, 20, 0, 10],
            ['check_in_7', 'Week Warrior', 'Reach a 7-day check-in streak', 'check_in', 'check_ins', 7, 100, 1, 50],
            ['check_in_30', 'Monthly Devotee', 'Reach a 30-day check-in streak', 'check_in', 'check_ins', 30, 500, 5, 200],
            ['first_mentorship', 'Mentor Bond', 'Start your first mentorship', 'social', 'mentorships', 1, 50, 0, 25],
            ['gift_10', 'Generous Soul', 'Send 10 gifts', 'gifts', 'gifts_sent', 10, 80, 0, 40],
            ['followers_10', 'Rising Star', 'Reach 10 followers', 'social', 'followers', 10, 60, 0, 30],
        ];

        foreach ($rows as $i => [$code, $name, $desc, $category, $metric, $target, $coins, $diamonds, $xp]) {
            DB::table('achievements')->insert([
                'id' => (string) Str::uuid(),
                'code' => $code,
                'name' => $name,
                'description' => $desc,
                'icon' => null,
                'category' => $category,
                'metric' => $metric,
                'target' => $target,
                'coins_reward' => $coins,
                'diamonds_reward' => $diamonds,
                'xp_reward' => $xp,
                'active' => true,
                'sort_order' => $i + 1,
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_check_ins');
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('mentorship_sessions');
        Schema::dropIfExists('mentorships');
        Schema::dropIfExists('mentorship_requests');
    }
};

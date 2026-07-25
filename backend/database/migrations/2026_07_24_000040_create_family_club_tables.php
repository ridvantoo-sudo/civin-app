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
        Schema::create('families', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('logo', 2048)->nullable();
            $table->string('badge_key', 40)->default('bronze');
            $table->string('badge_url', 2048)->nullable();
            $table->unsignedInteger('level')->default(1);
            $table->unsignedBigInteger('xp')->default(0);
            $table->unsignedBigInteger('xp_to_next_level')->default(100);
            $table->string('status', 24)->default('active')->index();
            $table->unsignedInteger('members_count')->default(0);
            $table->unsignedInteger('max_members')->default(50);
            $table->unsignedBigInteger('ranking_score')->default(0);
            $table->unsignedBigInteger('total_gifts_received')->default(0);
            $table->unsignedBigInteger('total_gifts_sent')->default(0);
            $table->unsignedBigInteger('wallet_coins')->default(0);
            $table->unsignedBigInteger('wallet_diamonds')->default(0);
            $table->timestamps();
            $table->index(['status', 'ranking_score']);
            $table->index(['level', 'xp']);
        });

        Schema::create('family_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 24);
            $table->string('status', 24)->index();
            $table->string('message', 1000)->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->unsignedBigInteger('contribution_xp')->default(0);
            $table->unsignedBigInteger('contribution_gifts')->default(0);
            $table->timestamps();
            $table->unique(['family_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['family_id', 'status', 'role']);
        });

        Schema::create('family_invites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignUuid('invited_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('invited_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 32)->unique();
            $table->string('role', 24)->default('member');
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['family_id', 'status']);
        });

        Schema::create('family_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('body', 2000);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['family_id', 'created_at']);
        });

        Schema::create('family_missions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 64)->unique();
            $table->string('title', 150);
            $table->string('description', 1000)->nullable();
            $table->string('period', 24)->default('daily');
            $table->unsignedInteger('target_value')->default(1);
            $table->unsignedInteger('reward_xp')->default(10);
            $table->unsignedInteger('reward_coins')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('family_mission_progress', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignUuid('mission_id')->constrained('family_missions')->cascadeOnDelete();
            $table->string('period_key', 32);
            $table->unsignedInteger('progress')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
            $table->unique(['family_id', 'mission_id', 'period_key'], 'family_mission_progress_unique');
            $table->index(['family_id', 'period_key']);
        });

        Schema::create('family_gifts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('gift_id')->constrained('gifts')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('coins');
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['family_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
        });

        Schema::create('family_wallet_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->bigInteger('amount');
            $table->string('currency', 16)->default('coins');
            $table->string('note', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['family_id', 'created_at']);
        });

        $now = now();
        $missions = [
            ['key' => 'daily_chat', 'title' => 'Family Chat', 'description' => 'Send 5 family chat messages today.', 'period' => 'daily', 'target_value' => 5, 'reward_xp' => 20, 'reward_coins' => 0],
            ['key' => 'daily_gift', 'title' => 'Gift Your Family', 'description' => 'Send 1 gift to the family today.', 'period' => 'daily', 'target_value' => 1, 'reward_xp' => 40, 'reward_coins' => 10],
            ['key' => 'daily_contribute', 'title' => 'Fill the Wallet', 'description' => 'Contribute coins to the family wallet today.', 'period' => 'daily', 'target_value' => 1, 'reward_xp' => 25, 'reward_coins' => 0],
            ['key' => 'weekly_members', 'title' => 'Grow Together', 'description' => 'Welcome 2 new members this week.', 'period' => 'weekly', 'target_value' => 2, 'reward_xp' => 100, 'reward_coins' => 50],
        ];

        foreach ($missions as $mission) {
            DB::table('family_missions')->insert([
                'id' => (string) Str::uuid(),
                'key' => $mission['key'],
                'title' => $mission['title'],
                'description' => $mission['description'],
                'period' => $mission['period'],
                'target_value' => $mission['target_value'],
                'reward_xp' => $mission['reward_xp'],
                'reward_coins' => $mission['reward_coins'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('family_wallet_transactions');
        Schema::dropIfExists('family_gifts');
        Schema::dropIfExists('family_mission_progress');
        Schema::dropIfExists('family_missions');
        Schema::dropIfExists('family_messages');
        Schema::dropIfExists('family_invites');
        Schema::dropIfExists('family_members');
        Schema::dropIfExists('families');
    }
};

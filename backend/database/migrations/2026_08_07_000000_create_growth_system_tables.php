<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->unsignedInteger('uses_count')->default(0);
            $table->unsignedInteger('max_uses')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('referral_attributions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('invitee_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('referral_code_id')->constrained('referral_codes')->cascadeOnDelete();
            $table->string('status', 24)->default('rewarded')->index(); // pending|rewarded|revoked
            $table->unsignedBigInteger('referrer_coins')->default(0);
            $table->unsignedBigInteger('invitee_coins')->default(0);
            $table->unsignedBigInteger('referrer_diamonds')->default(0);
            $table->unsignedBigInteger('invitee_diamonds')->default(0);
            $table->timestamp('rewarded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'created_at']);
        });

        Schema::create('invites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('channel', 16)->default('link'); // link|email
            $table->string('target', 255)->nullable(); // email when channel=email
            $table->string('status', 24)->default('pending')->index(); // pending|accepted|expired|revoked
            $table->foreignUuid('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['inviter_id', 'status']);
        });

        Schema::create('invite_rewards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('invite_id')->constrained('invites')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 16); // inviter|invitee
            $table->unsignedBigInteger('coins')->default(0);
            $table->unsignedBigInteger('diamonds')->default(0);
            $table->string('status', 24)->default('granted'); // granted|reversed
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();

            $table->unique(['invite_id', 'user_id', 'role']);
        });

        Schema::create('promo_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();
            $table->string('reward_type', 24); // coins|diamonds|percent_off
            $table->unsignedBigInteger('reward_value'); // coins/diamonds amount OR percent (1-100)
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->boolean('active')->default(true)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['active', 'starts_at', 'ends_at']);
        });

        Schema::create('promo_redemptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('promo_code_id')->constrained('promo_codes')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reward_type', 24);
            $table->unsignedBigInteger('reward_value');
            $table->unsignedBigInteger('coins_awarded')->default(0);
            $table->unsignedBigInteger('diamonds_awarded')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['promo_code_id', 'user_id']);
        });

        Schema::create('growth_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('channel', 16); // push|email|both
            $table->string('status', 24)->default('draft')->index(); // draft|scheduled|sending|sent|cancelled
            $table->string('subject', 255)->nullable(); // email subject / push title
            $table->text('body');
            $table->string('action_url', 2048)->nullable();
            $table->json('audience')->nullable(); // {type: all|country|user_ids, ...}
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('targeted_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('growth_campaigns')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 16); // push|email
            $table->string('status', 24)->default('pending')->index(); // pending|sent|failed|skipped
            $table->string('provider_reference', 191)->nullable();
            $table->string('error_message', 512)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'user_id', 'channel']);
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_deliveries');
        Schema::dropIfExists('growth_campaigns');
        Schema::dropIfExists('promo_redemptions');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('invite_rewards');
        Schema::dropIfExists('invites');
        Schema::dropIfExists('referral_attributions');
        Schema::dropIfExists('referral_codes');
    }
};

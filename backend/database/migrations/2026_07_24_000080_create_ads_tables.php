<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_admin_campaign')->default(false)->index();
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('placement', 32)->index();
            $table->string('media_url', 2048)->nullable();
            $table->string('media_type', 16)->default('image');
            $table->string('cta_label', 80)->nullable();
            $table->string('cta_url', 2048)->nullable();
            $table->string('target_type', 32)->default('all')->index();
            $table->json('target_payload')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedInteger('priority')->default(0);
            $table->unsignedInteger('budget_impressions')->nullable();
            $table->unsignedInteger('budget_clicks')->nullable();
            $table->unsignedBigInteger('impressions_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['placement', 'status', 'priority']);
            $table->index(['owner_user_id', 'status']);
            $table->index(['starts_at', 'ends_at', 'status']);
        });

        Schema::create('ad_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 24)->index();
            $table->string('placement', 32)->index();
            $table->string('context_id', 64)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ad_campaign_id', 'event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_events');
        Schema::dropIfExists('ad_campaigns');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12 — Anti-Cheat platform storage.
 *
 * Strictly additive: creates new `security_*` tables only. No existing table is
 * altered, so every other Civin module keeps its current schema and behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('idempotency_key', 191)->nullable()->unique('security_events_idem_unique');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 96)->index();
            $table->string('source', 64)->index();
            $table->string('category', 64)->nullable()->index();
            $table->string('surface', 32)->nullable()->index();
            $table->uuid('subject_id')->nullable()->index();
            $table->string('subject_type', 96)->nullable();
            $table->string('device_hash', 128)->nullable()->index();
            $table->string('session_hash', 128)->nullable()->index();
            $table->string('ip_hash', 128)->nullable()->index();
            $table->string('country_code', 8)->nullable()->index();
            $table->string('platform', 32)->nullable()->index();
            $table->decimal('amount', 18, 4)->default(0);
            $table->string('currency', 16)->nullable();
            $table->decimal('risk_contribution', 8, 4)->default(0);
            $table->boolean('analyzed')->default(false)->index();
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'occurred_at'], 'security_events_user_time_idx');
            $table->index(['source', 'occurred_at'], 'security_events_source_time_idx');
            $table->index(['event', 'occurred_at'], 'security_events_event_time_idx');
            $table->index(['device_hash', 'occurred_at'], 'security_events_device_time_idx');
        });

        Schema::create('security_scores', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('security_score', 6, 2)->default(80);
            $table->decimal('trust_score', 6, 2)->default(70);
            $table->decimal('risk_score', 6, 2)->default(0);
            $table->decimal('fraud_score', 6, 2)->default(0);
            $table->decimal('bot_score', 6, 2)->default(0);
            $table->unsignedBigInteger('signal_count')->default(0);
            $table->unsignedBigInteger('alert_count')->default(0);
            $table->unsignedBigInteger('clean_streak_days')->default(0);
            $table->string('band', 24)->default('low')->index();
            $table->json('breakdown')->nullable();
            $table->timestamp('last_signal_at')->nullable()->index();
            $table->timestamp('last_decayed_at')->nullable();
            $table->timestamps();
            $table->index(['risk_score', 'updated_at'], 'security_scores_risk_idx');
            $table->index(['fraud_score', 'updated_at'], 'security_scores_fraud_idx');
            $table->index(['bot_score', 'updated_at'], 'security_scores_bot_idx');
        });

        Schema::create('security_devices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_hash', 128)->index();
            $table->string('hardware_hash', 128)->nullable()->index();
            $table->string('os_hash', 128)->nullable();
            $table->string('app_hash', 128)->nullable();
            $table->string('platform', 32)->nullable()->index();
            $table->string('os_version', 64)->nullable();
            $table->string('app_version', 32)->nullable();
            $table->string('model', 96)->nullable();
            $table->boolean('is_emulator')->default(false)->index();
            $table->boolean('is_rooted')->default(false)->index();
            $table->boolean('is_jailbroken')->default(false)->index();
            $table->boolean('is_debug_build')->default(false)->index();
            $table->boolean('is_tampered')->default(false)->index();
            $table->boolean('is_cloned')->default(false)->index();
            $table->boolean('is_trusted')->default(false)->index();
            $table->boolean('is_blocked')->default(false)->index();
            $table->decimal('risk_score', 6, 2)->default(0);
            $table->unsignedBigInteger('linked_account_count')->default(1);
            $table->unsignedBigInteger('seen_count')->default(1);
            $table->json('fingerprint')->nullable();
            $table->json('history')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['user_id', 'device_hash'], 'security_devices_user_device_unique');
            $table->index(['device_hash', 'last_seen_at'], 'security_devices_hash_time_idx');
        });

        Schema::create('security_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_hash', 128)->index();
            $table->string('device_hash', 128)->nullable()->index();
            $table->string('ip_hash', 128)->nullable()->index();
            $table->string('country_code', 8)->nullable();
            $table->string('platform', 32)->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->boolean('is_verified')->default(false)->index();
            $table->unsignedBigInteger('request_count')->default(0);
            $table->unsignedBigInteger('anomaly_count')->default(0);
            $table->decimal('risk_score', 6, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'session_hash'], 'security_sessions_user_hash_unique');
            $table->index(['user_id', 'status'], 'security_sessions_user_status_idx');
        });

        Schema::create('security_networks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('ip_hash', 128)->index();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('asn', 32)->nullable()->index();
            $table->string('organization', 128)->nullable();
            $table->string('country_code', 8)->nullable()->index();
            $table->string('region', 96)->nullable();
            $table->boolean('is_vpn')->default(false)->index();
            $table->boolean('is_proxy')->default(false)->index();
            $table->boolean('is_tor')->default(false)->index();
            $table->boolean('is_hosting')->default(false)->index();
            $table->boolean('is_blocked')->default(false)->index();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->decimal('packet_frequency', 12, 4)->default(0);
            $table->unsignedBigInteger('distinct_users')->default(1);
            $table->unsignedBigInteger('anomaly_count')->default(0);
            $table->decimal('risk_score', 6, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['ip_hash', 'user_id'], 'security_networks_ip_user_unique');
        });

        Schema::create('security_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 96)->unique();
            $table->string('name', 160);
            $table->string('description', 500)->nullable();
            $table->string('signal', 96)->index();
            $table->string('severity', 24)->default('medium')->index();
            $table->string('action', 24)->default('warn')->index();
            $table->decimal('threshold', 8, 2)->default(70);
            $table->unsignedInteger('priority')->default(500)->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->unsignedBigInteger('match_count')->default(0);
            $table->json('conditions')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_matched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('security_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('event_id')->nullable()->constrained('security_events')->nullOnDelete();
            $table->string('rule_key', 96)->nullable()->index();
            $table->string('signal', 96)->index();
            $table->string('title', 191);
            $table->string('severity', 24)->default('medium')->index();
            $table->string('surface', 32)->nullable()->index();
            $table->string('action', 24)->default('warn')->index();
            $table->string('status', 24)->default('open')->index();
            $table->decimal('confidence', 6, 2)->default(0);
            $table->decimal('risk_delta', 8, 4)->default(0);
            $table->string('device_hash', 128)->nullable()->index();
            $table->string('ip_hash', 128)->nullable()->index();
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity', 'created_at'], 'security_alerts_triage_idx');
            $table->index(['user_id', 'created_at'], 'security_alerts_user_time_idx');
        });

        Schema::create('security_flags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('flag', 96)->index();
            $table->string('severity', 24)->default('medium')->index();
            $table->string('surface', 32)->nullable()->index();
            $table->string('status', 24)->default('active')->index();
            $table->decimal('confidence', 6, 2)->default(0);
            $table->unsignedBigInteger('hit_count')->default(1);
            $table->json('evidence')->nullable();
            $table->timestamp('first_flagged_at')->nullable();
            $table->timestamp('last_flagged_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['user_id', 'flag'], 'security_flags_user_flag_unique');
        });

        Schema::create('security_bans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope', 32)->default('platform')->index();
            $table->string('action', 24)->default('suspend')->index();
            $table->string('reason', 500);
            $table->string('rule_key', 96)->nullable()->index();
            $table->string('status', 24)->default('active')->index();
            $table->boolean('is_automatic')->default(true)->index();
            $table->string('device_hash', 128)->nullable()->index();
            $table->string('ip_hash', 128)->nullable()->index();
            $table->json('evidence')->nullable();
            $table->foreignUuid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('lifted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status'], 'security_bans_user_status_idx');
            $table->index(['scope', 'status'], 'security_bans_scope_status_idx');
        });

        Schema::create('security_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('alert_id')->nullable()->constrained('security_alerts')->nullOnDelete();
            $table->string('queue', 32)->default('general')->index();
            $table->string('reason', 500);
            $table->string('status', 24)->default('pending')->index();
            $table->string('priority', 24)->default('normal')->index();
            $table->string('resolution', 32)->nullable()->index();
            $table->text('reviewer_notes')->nullable();
            $table->decimal('risk_score', 6, 2)->default(0);
            $table->json('context')->nullable();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority', 'created_at'], 'security_reviews_queue_idx');
        });

        Schema::create('security_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 32)->default('engine')->index();
            $table->string('level', 24)->default('info')->index();
            $table->string('action', 96)->index();
            $table->string('message', 500);
            $table->string('subject_type', 96)->nullable();
            $table->uuid('subject_id')->nullable()->index();
            $table->string('ip_hash', 128)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['channel', 'created_at'], 'security_logs_channel_time_idx');
            $table->index(['action', 'created_at'], 'security_logs_action_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_logs');
        Schema::dropIfExists('security_reviews');
        Schema::dropIfExists('security_bans');
        Schema::dropIfExists('security_flags');
        Schema::dropIfExists('security_alerts');
        Schema::dropIfExists('security_rules');
        Schema::dropIfExists('security_networks');
        Schema::dropIfExists('security_sessions');
        Schema::dropIfExists('security_devices');
        Schema::dropIfExists('security_scores');
        Schema::dropIfExists('security_events');
    }
};

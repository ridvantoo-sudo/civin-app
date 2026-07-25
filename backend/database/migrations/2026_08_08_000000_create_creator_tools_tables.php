<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('live_categories')->nullOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('live_rooms')->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('thumbnail', 2048)->nullable();
            $table->string('status', 24)->default('scheduled')->index(); // scheduled|reminded|started|cancelled|missed
            $table->timestamp('scheduled_at')->index();
            $table->string('timezone', 64)->default('UTC');
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['host_id', 'status', 'scheduled_at']);
        });

        Schema::create('stream_clips', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignUuid('host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('kind', 24)->default('clip')->index(); // clip|highlight
            $table->unsignedInteger('start_seconds')->default(0);
            $table->unsignedInteger('end_seconds');
            $table->unsignedInteger('duration_seconds');
            $table->string('status', 24)->default('processing')->index(); // processing|ready|failed
            $table->string('media_url', 2048)->nullable();
            $table->string('thumbnail_url', 2048)->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'kind', 'created_at']);
            $table->index(['host_id', 'created_at']);
        });

        Schema::create('stream_replays', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->unique()->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignUuid('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('status', 24)->default('processing')->index(); // processing|ready|failed|expired
            $table->unsignedBigInteger('duration_seconds')->default(0);
            $table->unsignedBigInteger('peak_viewers')->default(0);
            $table->string('media_url', 2048)->nullable();
            $table->string('thumbnail_url', 2048)->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['host_id', 'status', 'created_at']);
        });

        Schema::create('rtmp_ingest_credentials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('live_rooms')->nullOnDelete();
            $table->string('ingest_url', 512);
            $table->string('stream_key', 191)->unique();
            $table->string('status', 24)->default('active')->index(); // active|revoked|expired
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['host_id', 'status']);
        });

        Schema::create('stream_analytics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->unique()->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignUuid('host_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('duration_seconds')->default(0);
            $table->unsignedBigInteger('peak_viewers')->default(0);
            $table->unsignedBigInteger('unique_viewers')->default(0);
            $table->unsignedBigInteger('chat_messages')->default(0);
            $table->unsignedBigInteger('gifts_count')->default(0);
            $table->unsignedBigInteger('gifts_coins')->default(0);
            $table->unsignedBigInteger('new_followers')->default(0);
            $table->decimal('avg_watch_seconds', 12, 2)->default(0);
            $table->json('timeline')->nullable(); // optional minute buckets
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['host_id', 'finalized_at']);
        });

        Schema::create('stream_analytics_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->unsignedBigInteger('viewer_count')->default(0);
            $table->unsignedBigInteger('chat_messages')->default(0);
            $table->unsignedBigInteger('gifts_count')->default(0);
            $table->timestamp('captured_at')->index();
            $table->timestamps();

            $table->index(['room_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_analytics_snapshots');
        Schema::dropIfExists('stream_analytics');
        Schema::dropIfExists('rtmp_ingest_credentials');
        Schema::dropIfExists('stream_replays');
        Schema::dropIfExists('stream_clips');
        Schema::dropIfExists('stream_schedules');
    }
};

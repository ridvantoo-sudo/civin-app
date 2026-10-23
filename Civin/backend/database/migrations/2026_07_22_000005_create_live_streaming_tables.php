<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('icon', 2048)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->index(['status', 'sort_order']);
        });

        Schema::create('live_rooms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('live_categories')->restrictOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('thumbnail', 2048)->nullable();
            $table->unsignedInteger('stream_uid')->unique();
            $table->string('agora_channel_name', 64)->unique();
            $table->enum('status', ['created', 'live', 'ended'])->default('created')->index();
            $table->unsignedBigInteger('viewer_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->index(['status', 'started_at']);
            $table->index(['host_id', 'status']);
            $table->index(['category_id', 'status']);
        });

        Schema::create('live_viewers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->unique(['room_id', 'user_id']);
            $table->index(['room_id', 'left_at']);
            $table->index(['user_id', 'left_at']);
        });

        Schema::create('live_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->unique()->constrained('live_rooms')->cascadeOnDelete();
            $table->unsignedBigInteger('duration')->default(0);
            $table->unsignedBigInteger('peak_viewers')->default(0);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_sessions');
        Schema::dropIfExists('live_viewers');
        Schema::dropIfExists('live_rooms');
        Schema::dropIfExists('live_categories');
    }
};

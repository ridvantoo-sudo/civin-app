<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_rooms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('thumbnail', 2048)->nullable();
            $table->unsignedInteger('host_uid')->unique();
            $table->string('agora_channel_name', 64)->unique();
            $table->string('status', 24)->default('live')->index();
            $table->unsignedTinyInteger('seat_count')->default(8);
            $table->unsignedBigInteger('participant_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->index(['status', 'started_at']);
            $table->index(['host_id', 'status']);
        });

        Schema::create('voice_seats', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->constrained('voice_rooms')->cascadeOnDelete();
            $table->unsignedTinyInteger('seat_index');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('empty')->index();
            $table->boolean('is_muted')->default(false);
            $table->unsignedInteger('stream_uid')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['room_id', 'seat_index']);
            $table->index(['room_id', 'status']);
            $table->index(['room_id', 'user_id']);
        });

        Schema::create('voice_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->constrained('voice_rooms')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 24)->default('audience');
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->unique(['room_id', 'user_id']);
            $table->index(['room_id', 'left_at']);
            $table->index(['user_id', 'left_at']);
        });

        Schema::create('voice_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->unique()->constrained('voice_rooms')->cascadeOnDelete();
            $table->unsignedBigInteger('duration')->default(0);
            $table->unsignedBigInteger('peak_participants')->default(0);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_sessions');
        Schema::dropIfExists('voice_participants');
        Schema::dropIfExists('voice_seats');
        Schema::dropIfExists('voice_rooms');
    }
};

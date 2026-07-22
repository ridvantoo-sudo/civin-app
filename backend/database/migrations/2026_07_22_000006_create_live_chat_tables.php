<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message', 500);
            $table->string('type', 20)->default('TEXT')->index();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['room_id', 'created_at']);
            $table->index(['room_id', 'user_id', 'created_at']);
            $table->index(['room_id', 'type']);
        });

        Schema::create('live_chat_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->unique()->constrained('live_rooms')->cascadeOnDelete();
            $table->unsignedInteger('slow_mode_seconds')->default(0);
            $table->boolean('followers_only')->default(false);
            $table->boolean('allow_links')->default(true);
            $table->timestamps();
        });

        Schema::create('live_chat_moderators', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->default('moderator');
            $table->timestamps();
            $table->unique(['room_id', 'user_id']);
            $table->index(['room_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_chat_moderators');
        Schema::dropIfExists('live_chat_settings');
        Schema::dropIfExists('live_messages');
    }
};

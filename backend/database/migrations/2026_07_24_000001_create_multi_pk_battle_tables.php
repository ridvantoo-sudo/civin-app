<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multi_pk_battles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('initiator_id')->constrained('users')->cascadeOnDelete();
            $table->string('mode', 16);
            $table->string('status', 16)->index();
            $table->unsignedInteger('duration_seconds');
            $table->unsignedInteger('countdown_seconds')->default(3);
            $table->timestamp('invitation_expires_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignUuid('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('winning_team')->nullable();
            $table->timestamps();
            $table->index(['status', 'invitation_expires_at']);
            $table->index(['status', 'started_at']);
        });

        Schema::create('multi_pk_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('multi_pk_battle_id')->constrained('multi_pk_battles')->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('team')->nullable();
            $table->boolean('accepted')->default(false);
            $table->boolean('active')->default(false);
            $table->unsignedBigInteger('score')->default(0);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['multi_pk_battle_id', 'room_id']);
            $table->unique(['multi_pk_battle_id', 'user_id']);
            $table->index(['room_id', 'active']);
            $table->index(['user_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multi_pk_participants');
        Schema::dropIfExists('multi_pk_battles');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_pk_battles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('initiator_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16)->index();
            $table->unsignedInteger('duration_seconds');
            $table->unsignedInteger('countdown_seconds');
            $table->timestamp('invitation_expires_at');
            $table->timestamp('countdown_started_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignUuid('winner_room_id')->nullable()->constrained('voice_rooms')->nullOnDelete();
            $table->boolean('is_draw')->default(false);
            $table->timestamps();
            $table->index(['status', 'invitation_expires_at']);
            $table->index(['status', 'ends_at']);
        });

        Schema::create('voice_pk_teams', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('voice_pk_battle_id')->constrained('voice_pk_battles')->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained('voice_rooms')->cascadeOnDelete();
            $table->foreignUuid('host_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('accepted')->default(false);
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedBigInteger('team_score')->default(0);
            $table->unsignedBigInteger('gift_score')->default(0);
            $table->timestamps();
            $table->unique(['voice_pk_battle_id', 'room_id']);
            $table->index(['room_id', 'accepted']);
        });

        Schema::create('voice_pk_gift_contributions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('voice_pk_battle_id')->constrained('voice_pk_battles')->cascadeOnDelete();
            $table->foreignUuid('voice_pk_team_id')->constrained('voice_pk_teams')->cascadeOnDelete();
            $table->foreignUuid('gift_transaction_id')->constrained('gift_transactions')->cascadeOnDelete();
            $table->unsignedBigInteger('coins');
            $table->timestamp('created_at');
            $table->unique('gift_transaction_id');
            $table->index(['voice_pk_battle_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_pk_gift_contributions');
        Schema::dropIfExists('voice_pk_teams');
        Schema::dropIfExists('voice_pk_battles');
    }
};

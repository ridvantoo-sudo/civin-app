<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_transactions', function (Blueprint $table): void {
            $table->uuid('room_id')->nullable()->change();
            $table->foreignUuid('voice_room_id')
                ->nullable()
                ->after('room_id')
                ->constrained('voice_rooms')
                ->cascadeOnDelete();
            $table->index(['voice_room_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('gift_transactions', function (Blueprint $table): void {
            $table->dropForeign(['voice_room_id']);
            $table->dropIndex(['voice_room_id', 'created_at']);
            $table->dropColumn('voice_room_id');
            $table->uuid('room_id')->nullable(false)->change();
        });
    }
};

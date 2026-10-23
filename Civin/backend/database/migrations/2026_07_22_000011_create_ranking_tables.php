<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rankings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 32);
            $table->string('period', 16);
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('score')->default(0);
            $table->unsignedInteger('rank');
            $table->date('date');
            $table->timestamp('created_at');
            $table->unique(['type', 'period', 'date', 'user_id']);
            $table->index(['type', 'period', 'date', 'rank']);
            $table->index(['user_id', 'type', 'period']);
        });

        Schema::create('ranking_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 32);
            $table->string('period', 16);
            $table->json('data');
            $table->timestamp('created_at');
            $table->index(['type', 'period', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_snapshots');
        Schema::dropIfExists('rankings');
    }
};

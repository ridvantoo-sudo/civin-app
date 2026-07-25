<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40)->index(); // moderate_text|moderate_voice|translate|recommend|highlight_suggest
            $table->string('status', 24)->default('queued')->index(); // queued|processing|completed|failed
            $table->string('provider', 40)->default('fake');
            $table->json('input')->nullable();
            $table->json('result')->nullable();
            $table->string('error', 1000)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });

        Schema::create('ai_moderation_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')->nullable()->constrained('ai_jobs')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('surface', 40)->default('chat'); // chat|post|voice|live
            $table->string('subject_type', 80)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('verdict', 16); // allow|flag|block
            $table->json('labels')->nullable();
            $table->decimal('confidence', 5, 4)->default(0);
            $table->string('provider', 40)->default('fake');
            $table->timestamps();

            $table->index(['surface', 'verdict']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_moderation_logs');
        Schema::dropIfExists('ai_jobs');
    }
};

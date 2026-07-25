<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->string('document_number', 64);
            $table->string('full_legal_name', 191);
            $table->string('nationality', 64)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('document_front_path');
            $table->string('document_back_path')->nullable();
            $table->string('selfie_path');
            $table->decimal('face_match_score', 5, 2)->nullable();
            $table->string('face_match_status', 32)->default('pending');
            $table->string('status', 32)->index();
            $table->boolean('withdrawal_verified')->default(false);
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique('user_id');
            $table->index(['status', 'created_at']);
            $table->index(['document_type', 'status']);
            $table->index(['face_match_status', 'status']);
        });

        Schema::create('kyc_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('kyc_submission_id')
                ->constrained('kyc_submissions')
                ->cascadeOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 48);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['kyc_submission_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_audit_logs');
        Schema::dropIfExists('kyc_submissions');
    }
};

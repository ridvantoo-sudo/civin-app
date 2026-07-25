<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_recharge_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('coins');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 8)->default('USD');
            $table->string('reference_number', 191);
            $table->string('receipt_path');
            $table->string('status', 24)->index();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['reference_number']);
        });

        Schema::create('offline_recharge_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('offline_recharge_request_id')
                ->constrained('offline_recharge_requests')
                ->cascadeOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 32);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['offline_recharge_request_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_recharge_audit_logs');
        Schema::dropIfExists('offline_recharge_requests');
    }
};

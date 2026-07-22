<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('logo', 2048)->nullable();
            $table->decimal('commission_rate', 5, 2)->default(10);
            $table->string('status', 24)->default('active')->index();
            $table->unsignedInteger('members_count')->default(0);
            $table->unsignedInteger('hosts_count')->default(0);
            $table->unsignedBigInteger('total_gross_earnings')->default(0);
            $table->unsignedBigInteger('total_commission')->default(0);
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('agency_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 24);
            $table->string('status', 24)->index();
            $table->string('message', 1000)->nullable();
            $table->timestamp('applied_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->unsignedBigInteger('gross_earnings')->default(0);
            $table->unsignedBigInteger('commission_paid')->default(0);
            $table->timestamps();
            $table->unique(['agency_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['agency_id', 'status', 'role']);
        });

        Schema::create('agency_commissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignUuid('host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('agency_member_id')->constrained('agency_members')->cascadeOnDelete();
            $table->uuidMorphs('source');
            $table->unsignedBigInteger('gross_amount');
            $table->decimal('commission_rate', 5, 2);
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('host_net_amount');
            $table->string('currency', 16)->default('diamonds');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->unique(['source_type', 'source_id']);
            $table->index(['agency_id', 'created_at']);
            $table->index(['host_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_commissions');
        Schema::dropIfExists('agency_members');
        Schema::dropIfExists('agencies');
    }
};

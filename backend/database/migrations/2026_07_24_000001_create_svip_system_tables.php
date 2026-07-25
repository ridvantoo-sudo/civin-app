<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('svip_levels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->unsignedInteger('level')->unique();
            $table->unsignedBigInteger('coin_price');
            $table->unsignedInteger('duration_days');
            $table->string('badge', 2048)->nullable();
            $table->string('animated_profile', 2048)->nullable();
            $table->string('profile_frame', 2048)->nullable();
            $table->string('chat_effect', 2048)->nullable();
            $table->string('entrance_animation', 2048)->nullable();
            $table->boolean('exclusive_gifts')->default(true);
            $table->boolean('exclusive_frames')->default(true);
            $table->boolean('special_privileges')->default(true);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->index(['status', 'sort_order']);
            $table->index(['status', 'level']);
        });

        Schema::create('user_svips', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('svip_level_id')->constrained('svip_levels')->restrictOnDelete();
            $table->string('status', 24)->index();
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });

        Schema::create('svip_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('svip_level_id')->constrained('svip_levels')->restrictOnDelete();
            $table->foreignUuid('user_svip_id')->nullable()->constrained('user_svips')->nullOnDelete();
            $table->string('type', 32);
            $table->unsignedBigInteger('coins');
            $table->unsignedInteger('from_level')->nullable();
            $table->unsignedInteger('to_level');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['svip_level_id', 'created_at']);
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->boolean('is_svip')->default(false)->after('is_vip');
            $table->index('is_svip');
        });

        Schema::table('gifts', function (Blueprint $table): void {
            $table->string('membership_required', 16)->nullable()->after('status');
            $table->index('membership_required');
        });
    }

    public function down(): void
    {
        Schema::table('gifts', function (Blueprint $table): void {
            $table->dropIndex(['membership_required']);
            $table->dropColumn('membership_required');
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropIndex(['is_svip']);
            $table->dropColumn('is_svip');
        });

        Schema::dropIfExists('svip_transactions');
        Schema::dropIfExists('user_svips');
        Schema::dropIfExists('svip_levels');
    }
};

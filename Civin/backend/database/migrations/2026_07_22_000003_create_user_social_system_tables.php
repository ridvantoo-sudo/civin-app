<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->index()->after('status');
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->string('cover_image_url')->nullable()->after('avatar_url');
            $table->unsignedInteger('level')->default(1)->after('gender');
            $table->boolean('is_vip')->default(false)->index()->after('level');
            $table->boolean('is_private')->default(false)->index()->after('is_vip');
            $table->unsignedBigInteger('followers_count')->default(0)->after('is_private');
            $table->unsignedBigInteger('following_count')->default(0)->after('followers_count');
            $table->unsignedBigInteger('likes_count')->default(0)->after('following_count');
        });

        Schema::create('followers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('followed_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted'])->default('pending')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['follower_id', 'followed_id']);
            $table->index(['followed_id', 'status', 'created_at']);
            $table->index(['follower_id', 'status', 'created_at']);
        });

        Schema::create('blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['blocker_id', 'blocked_id']);
            $table->index(['blocked_id', 'created_at']);
        });

        Schema::create('reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('reported_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('category', ['spam', 'harassment', 'hate_speech', 'impersonation', 'nudity', 'violence', 'other'])->index();
            $table->text('details')->nullable();
            $table->enum('status', ['pending', 'reviewing', 'resolved', 'dismissed'])->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['reporter_id', 'created_at']);
            $table->index(['reported_user_id', 'status']);
        });

        Schema::create('user_status', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_online')->default(false)->index();
            $table->boolean('is_live')->default(false)->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('live_started_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_status');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('followers');

        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropIndex(['is_vip']);
            $table->dropIndex(['is_private']);
            $table->dropColumn([
                'cover_image_url',
                'level',
                'is_vip',
                'is_private',
                'followers_count',
                'following_count',
                'likes_count',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_admin']);
            $table->dropColumn('is_admin');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_compliance_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('country_code', 2)->index(); // ISO alpha-2, or * for all
            $table->string('region_code', 16)->nullable()->index();
            $table->string('feature_key', 64)->default('*')->index(); // *|live|payments|games|chat|marketplace
            $table->string('action', 16)->default('allow'); // allow|deny|restrict
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('active')->default(true)->index();
            $table->string('reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['country_code', 'feature_key', 'active']);
        });

        Schema::create('user_timezone_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('timezone', 64);
            $table->timestamps();
        });

        Schema::create('timezone_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->timestamp('run_at_utc');
            $table->string('timezone', 64);
            $table->string('local_wall_time', 32)->nullable(); // e.g. 2026-08-13 18:00:00
            $table->string('status', 24)->default('scheduled')->index(); // scheduled|done|cancelled
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'run_at_utc']);
        });

        Schema::create('cdn_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 40)->unique()->default('default');
            $table->string('base_url', 2048);
            $table->string('public_base_url', 2048)->nullable();
            $table->unsignedInteger('cache_ttl_seconds')->default(86400);
            $table->json('image_params')->nullable();
            $table->json('prefixes')->nullable();
            $table->json('edge_map')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $this->seedDefaults();
    }

    private function seedDefaults(): void
    {
        $now = now();

        $rules = [
            ['IQ', null, '*', 'allow', 13, 10, 'Iraq default allow with age 13+'],
            ['IQ', 'IQ-KRI', 'games', 'restrict', 16, 5, 'KRI games require 16+'],
            ['SA', null, 'live', 'restrict', 18, 20, 'SA live streaming age gate'],
            ['CN', null, 'payments', 'deny', null, 1, 'Payments blocked in CN stub'],
            ['*', null, '*', 'allow', 13, 1000, 'Global fallback'],
        ];

        foreach ($rules as [$country, $region, $feature, $action, $minAge, $priority, $reason]) {
            DB::table('geo_compliance_rules')->insert([
                'id' => (string) Str::uuid(),
                'country_code' => $country,
                'region_code' => $region,
                'feature_key' => $feature,
                'action' => $action,
                'min_age' => $minAge,
                'priority' => $priority,
                'active' => true,
                'reason' => $reason,
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('cdn_configs')->insert([
            'id' => (string) Str::uuid(),
            'key' => 'default',
            'base_url' => 'https://cdn.civin.app',
            'public_base_url' => 'https://cdn.civin.app',
            'cache_ttl_seconds' => 86400,
            'image_params' => json_encode(['width' => 1080, 'quality' => 80, 'format' => 'webp'], JSON_THROW_ON_ERROR),
            'prefixes' => json_encode([
                'avatars' => '/avatars',
                'marketplace' => '/marketplace',
                'live' => '/live',
                'stickers' => '/stickers',
            ], JSON_THROW_ON_ERROR),
            'edge_map' => json_encode([
                'IQ' => 'me-central-1',
                'TR' => 'eu-central-1',
                'DEFAULT' => 'eu-central-1',
            ], JSON_THROW_ON_ERROR),
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cdn_configs');
        Schema::dropIfExists('timezone_schedules');
        Schema::dropIfExists('user_timezone_preferences');
        Schema::dropIfExists('geo_compliance_rules');
    }
};

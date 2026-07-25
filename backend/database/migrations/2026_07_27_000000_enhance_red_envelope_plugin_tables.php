<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive upgrade for the Red Envelope production plugin.
 *
 * The legacy columns (sender_id, total_coins, remaining_coins, total_slots,
 * remaining_slots, coins) are preserved so the legacy `/games/play` flow in
 * GameService keeps working untouched. Every new column is nullable or has a
 * default, and existing rows are backfilled at the end of `up()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('red_envelope_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('min_coins_amount')->default(100);
            $table->unsignedInteger('max_coins_amount')->default(1000000);
            $table->unsignedInteger('min_diamonds_amount')->default(10);
            $table->unsignedInteger('max_diamonds_amount')->default(100000);
            $table->unsignedInteger('max_winners')->default(100);
            $table->unsignedInteger('min_duration_seconds')->default(60);
            $table->unsignedInteger('max_duration_seconds')->default(86400);
            $table->unsignedInteger('default_duration_seconds')->default(3600);
            $table->unsignedInteger('max_created_per_day')->nullable();
            $table->boolean('refund_unclaimed')->default(true);
            $table->json('allowed_reward_types')->nullable();
            $table->json('allowed_distribution_types')->nullable();
            $table->json('allowed_visibilities')->nullable();
            $table->json('allowed_claim_scopes')->nullable();
            $table->json('allowed_room_types')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->string('status', 16)->default('active')->index();
            $table->boolean('is_default')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'is_default']);
        });

        Schema::table('red_envelopes', function (Blueprint $table): void {
            $table->foreignUuid('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('room_id')->nullable();
            $table->string('room_type', 24)->nullable()->index();
            $table->string('reward_type', 16)->default('coins')->index();
            $table->string('distribution_type', 16)->default('random');
            $table->string('visibility', 24)->default('public')->index();
            $table->string('claim_scope', 24)->default('everyone');
            $table->json('whitelist_user_ids')->nullable();
            $table->unsignedInteger('total_amount')->nullable();
            $table->unsignedInteger('remaining_amount')->nullable();
            $table->unsignedInteger('winner_count')->nullable();
            $table->unsignedInteger('remaining_count')->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->foreignUuid('game_play_id')->nullable()->constrained('game_plays')->nullOnDelete();
            $table->uuid('wallet_transaction_id')->nullable();
            $table->foreignUuid('config_id')->nullable()->constrained('red_envelope_configs')->nullOnDelete();
            $table->json('metadata')->nullable();
        });

        Schema::table('red_envelopes', function (Blueprint $table): void {
            // NULL idempotency keys stay distinct on every supported driver, so
            // legacy rows created by GameService are unaffected.
            $table->unique(['sender_id', 'idempotency_key'], 'red_envelopes_idempotency_unique');
            $table->index(['creator_id', 'created_at'], 'red_envelopes_creator_created_index');
            $table->index(['room_type', 'room_id', 'status'], 'red_envelopes_room_status_index');
            $table->index(['status', 'expires_at'], 'red_envelopes_status_expires_index');
        });

        Schema::table('red_envelope_claims', function (Blueprint $table): void {
            $table->unsignedInteger('reward_amount')->nullable();
            $table->string('reward_type', 16)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->uuid('wallet_transaction_id')->nullable();
            $table->foreignUuid('play_id')->nullable()->constrained('game_plays')->nullOnDelete();
            $table->json('metadata')->nullable();
        });

        Schema::table('red_envelope_claims', function (Blueprint $table): void {
            $table->unique(['user_id', 'idempotency_key'], 'red_envelope_claims_idempotency_unique');
            $table->index(['user_id', 'claimed_at'], 'red_envelope_claims_user_claimed_index');
        });

        DB::table('red_envelopes')->update([
            'creator_id' => DB::raw('sender_id'),
            'total_amount' => DB::raw('total_coins'),
            'remaining_amount' => DB::raw('remaining_coins'),
            'winner_count' => DB::raw('total_slots'),
            'remaining_count' => DB::raw('remaining_slots'),
            'reward_type' => 'coins',
            'distribution_type' => 'random',
            'visibility' => 'public',
            'claim_scope' => 'everyone',
        ]);

        DB::table('red_envelope_claims')->update([
            'reward_amount' => DB::raw('coins'),
            'reward_type' => 'coins',
            'claimed_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('red_envelope_claims', function (Blueprint $table): void {
            $table->dropUnique('red_envelope_claims_idempotency_unique');
            $table->dropIndex('red_envelope_claims_user_claimed_index');
        });

        Schema::table('red_envelope_claims', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('play_id');
            $table->dropColumn([
                'reward_amount',
                'reward_type',
                'claimed_at',
                'idempotency_key',
                'wallet_transaction_id',
                'metadata',
            ]);
        });

        Schema::table('red_envelopes', function (Blueprint $table): void {
            $table->dropUnique('red_envelopes_idempotency_unique');
            $table->dropIndex('red_envelopes_creator_created_index');
            $table->dropIndex('red_envelopes_room_status_index');
            $table->dropIndex('red_envelopes_status_expires_index');
        });

        Schema::table('red_envelopes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('creator_id');
            $table->dropConstrainedForeignId('game_play_id');
            $table->dropConstrainedForeignId('config_id');
            $table->dropColumn([
                'room_id',
                'room_type',
                'reward_type',
                'distribution_type',
                'visibility',
                'claim_scope',
                'whitelist_user_ids',
                'total_amount',
                'remaining_amount',
                'winner_count',
                'remaining_count',
                'idempotency_key',
                'wallet_transaction_id',
                'metadata',
            ]);
        });

        Schema::dropIfExists('red_envelope_configs');
    }
};

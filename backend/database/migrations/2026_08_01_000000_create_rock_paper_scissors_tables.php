<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive Rock Paper Scissors plugin schema.
 * Players are modelled through challenger/opponent columns on rps_matches, so
 * no separate participant table is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rps_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('min_entry_fee_coins')->default(0);
            $table->unsignedInteger('max_entry_fee_coins')->default(5000);
            $table->unsignedInteger('default_entry_fee_coins')->default(10);
            $table->unsignedInteger('default_round_timeout_seconds')->default(15);
            $table->unsignedInteger('min_round_timeout_seconds')->default(5);
            $table->unsignedInteger('max_round_timeout_seconds')->default(120);
            $table->unsignedInteger('accept_timeout_seconds')->default(120);
            $table->unsignedInteger('match_timeout_seconds')->default(1800);
            $table->unsignedTinyInteger('max_rounds')->default(11);
            $table->unsignedTinyInteger('forfeit_after_missed_rounds')->default(2);
            $table->unsignedTinyInteger('rematch_limit')->default(5);
            $table->unsignedInteger('max_created_per_day')->nullable();
            $table->unsignedInteger('cooldown_seconds')->default(0);
            $table->unsignedInteger('house_rake_bps')->default(0);
            $table->decimal('vip_reward_multiplier', 5, 2)->default(1.25);
            $table->json('modes_enabled')->nullable();
            $table->json('matchmaking_enabled')->nullable();
            $table->json('reward_types_enabled')->nullable();
            $table->json('visibilities_enabled')->nullable();
            $table->json('eligibilities_enabled')->nullable();
            $table->json('tournament_formats_enabled')->nullable();
            $table->json('move_rules')->nullable();
            $table->json('mode_rounds')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('status', 16)->default('active')->index();
            $table->boolean('is_default')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'is_default']);
        });

        Schema::create('rps_tournaments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('config_id')->nullable()->constrained('rps_configs')->nullOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->string('slug', 64)->unique();
            $table->string('name', 160);
            $table->string('description', 1000)->nullable();
            $table->string('format', 32)->default('single_elimination')->index();
            $table->string('status', 24)->default('scheduled')->index();
            $table->string('mode', 32)->default('best_of_3');
            $table->unsignedTinyInteger('rounds')->default(0);
            $table->unsignedTinyInteger('current_round')->default(0);
            $table->unsignedInteger('max_players')->default(16);
            $table->unsignedInteger('min_players')->default(2);
            $table->unsignedInteger('registered_count')->default(0);
            $table->unsignedInteger('entry_fee_coins')->default(0);
            $table->string('reward_type', 32)->default('coins');
            $table->unsignedInteger('reward_pool')->default(0);
            $table->json('prize_structure')->nullable();
            $table->boolean('is_vip')->default(false)->index();
            $table->boolean('is_daily')->default(false)->index();
            $table->boolean('is_scheduled')->default(true);
            $table->json('eligibility')->nullable();
            $table->json('participant_ids')->nullable();
            $table->string('bracket_seed', 64)->nullable();
            $table->boolean('brackets_generated')->default(false);
            $table->foreignUuid('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
            $table->index(['is_daily', 'status']);
        });

        Schema::create('rps_matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('config_id')->nullable()->constrained('rps_configs')->nullOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignUuid('tournament_id')->nullable()->constrained('rps_tournaments')->nullOnDelete();
            // Linked after rps_brackets exists; kept unconstrained to avoid a circular FK.
            $table->uuid('bracket_id')->nullable()->index();
            $table->uuid('parent_match_id')->nullable()->index();
            $table->foreignUuid('challenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('opponent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('loser_id')->nullable()->index();
            $table->uuid('forfeited_by')->nullable();
            $table->string('idempotency_key', 64);
            // Separate handle so the accepting player gets replay protection too.
            $table->string('opponent_idempotency_key', 64)->nullable();
            $table->string('mode', 32)->default('classic_1v1')->index();
            $table->string('matchmaking', 32)->default('private')->index();
            $table->string('status', 24)->default('pending')->index();
            $table->string('result', 24)->nullable()->index();
            $table->string('visibility', 32)->default('private')->index();
            $table->string('eligibility', 32)->default('everyone')->index();
            $table->string('reward_type', 32)->default('coins');
            $table->unsignedInteger('entry_fee_coins')->default(0);
            $table->unsignedInteger('pot_coins')->default(0);
            $table->unsignedInteger('rake_coins')->default(0);
            $table->unsignedTinyInteger('rounds_target')->default(1);
            $table->unsignedTinyInteger('wins_required')->default(1);
            $table->unsignedTinyInteger('max_rounds')->default(11);
            $table->unsignedTinyInteger('rounds_played')->default(0);
            $table->unsignedTinyInteger('challenger_score')->default(0);
            $table->unsignedTinyInteger('opponent_score')->default(0);
            $table->unsignedTinyInteger('draw_count')->default(0);
            $table->unsignedTinyInteger('challenger_missed')->default(0);
            $table->unsignedTinyInteger('opponent_missed')->default(0);
            $table->unsignedTinyInteger('current_round')->default(0);
            $table->uuid('current_round_id')->nullable();
            $table->unsignedInteger('round_timeout_seconds')->default(15);
            $table->string('room_id', 64)->nullable()->index();
            $table->string('room_type', 32)->nullable()->index();
            $table->boolean('is_vip_arena')->default(false)->index();
            $table->boolean('is_rematch')->default(false);
            $table->unsignedTinyInteger('rematch_count')->default(0);
            $table->unsignedInteger('config_version')->default(1);
            $table->json('move_rules')->nullable();
            $table->json('whitelist_user_ids')->nullable();
            $table->json('audit_log')->nullable();
            $table->json('tamper_flags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('accept_deadline_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->unique(['challenger_id', 'idempotency_key'], 'rps_matches_idempotency_unique');
            $table->unique(['opponent_id', 'opponent_idempotency_key'], 'rps_matches_opponent_idempotency_unique');
            $table->index(['status', 'mode', 'created_at']);
            $table->index(['status', 'matchmaking', 'entry_fee_coins'], 'rps_matches_open_lookup');
            $table->index(['tournament_id', 'status']);
            $table->index(['opponent_id', 'status']);
        });

        Schema::create('rps_rounds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('match_id')->constrained('rps_matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('round_index');
            $table->string('status', 24)->default('active')->index();
            $table->string('result', 24)->nullable();
            $table->foreignUuid('winner_id')->nullable()->constrained('users')->nullOnDelete();
            // Move rows are written after the round exists; unconstrained to avoid a circular FK.
            $table->uuid('challenger_move_id')->nullable();
            $table->uuid('opponent_move_id')->nullable();
            $table->boolean('challenger_submitted')->default(false);
            $table->boolean('opponent_submitted')->default(false);
            $table->boolean('revealed')->default(false);
            $table->string('challenger_move', 32)->nullable();
            $table->string('opponent_move', 32)->nullable();
            $table->string('server_seed', 64)->nullable();
            $table->json('audit')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('revealed_at')->nullable();
            $table->timestamps();
            $table->unique(['match_id', 'round_index'], 'rps_rounds_match_index_unique');
            $table->index(['status', 'ends_at']);
        });

        Schema::create('rps_moves', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('match_id')->constrained('rps_matches')->cascadeOnDelete();
            $table->foreignUuid('round_id')->constrained('rps_rounds')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('idempotency_key', 64);
            $table->string('role', 16);
            $table->unsignedTinyInteger('round_index')->default(0);
            $table->string('move', 32)->nullable();
            $table->string('move_hash', 128)->nullable();
            $table->string('server_seed', 64)->nullable();
            $table->boolean('is_auto')->default(false);
            $table->boolean('timed_out')->default(false);
            $table->unsignedInteger('client_response_ms')->nullable();
            $table->unsignedInteger('response_ms')->nullable();
            $table->json('audit')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('revealed_at')->nullable();
            $table->timestamps();
            $table->unique(['round_id', 'user_id'], 'rps_moves_round_user_unique');
            $table->unique(['user_id', 'idempotency_key'], 'rps_moves_idempotency_unique');
            $table->index(['match_id', 'round_index']);
        });

        Schema::create('rps_brackets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tournament_id')->constrained('rps_tournaments')->cascadeOnDelete();
            $table->foreignUuid('match_id')->nullable()->constrained('rps_matches')->nullOnDelete();
            $table->string('bracket_type', 24)->default('winners')->index();
            $table->unsignedTinyInteger('round_index')->default(0);
            $table->unsignedInteger('slot_index')->default(0);
            $table->string('status', 24)->default('pending')->index();
            $table->uuid('player_one_id')->nullable()->index();
            $table->uuid('player_two_id')->nullable()->index();
            $table->uuid('winner_id')->nullable()->index();
            $table->uuid('loser_id')->nullable();
            $table->uuid('next_bracket_id')->nullable();
            $table->uuid('next_loser_bracket_id')->nullable();
            $table->boolean('is_bye')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(
                ['tournament_id', 'bracket_type', 'round_index', 'slot_index'],
                'rps_brackets_position_unique',
            );
            $table->index(['tournament_id', 'status']);
        });

        Schema::create('rps_rewards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('match_id')->nullable()->constrained('rps_matches')->cascadeOnDelete();
            $table->foreignUuid('tournament_id')->nullable()->constrained('rps_tournaments')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('rps_configs')->nullOnDelete();
            $table->string('reward_type', 32)->default('coins')->index();
            $table->unsignedInteger('amount')->default(0);
            $table->string('currency', 16)->nullable();
            $table->string('reference', 64)->nullable();
            $table->unsignedTinyInteger('rank')->nullable();
            $table->boolean('is_vip')->default(false);
            $table->boolean('is_tournament')->default(false);
            $table->boolean('is_refund')->default(false);
            $table->string('status', 24)->default('granted')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['match_id', 'status']);
        });

        Schema::create('rps_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('match_id')->nullable()->constrained('rps_matches')->nullOnDelete();
            $table->foreignUuid('tournament_id')->nullable()->constrained('rps_tournaments')->nullOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('rps_configs')->nullOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignUuid('play_id')->nullable()->constrained('game_plays')->nullOnDelete();
            $table->uuid('opponent_id')->nullable()->index();
            $table->string('mode', 32)->nullable();
            $table->string('matchmaking', 32)->nullable();
            $table->string('result', 24)->default('finished');
            $table->unsignedTinyInteger('rounds_won')->default(0);
            $table->unsignedTinyInteger('rounds_lost')->default(0);
            $table->unsignedTinyInteger('rounds_drawn')->default(0);
            $table->unsignedInteger('entry_fee_coins')->default(0);
            $table->unsignedInteger('reward_coins')->default(0);
            $table->unsignedInteger('reward_diamonds')->default(0);
            $table->boolean('is_winner')->default(false);
            $table->json('snapshot')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['match_id', 'user_id']);
        });

        Schema::create('rps_statistics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('rps_configs')->nullOnDelete();
            $table->unsignedBigInteger('matches_played')->default(0);
            $table->unsignedBigInteger('matches_won')->default(0);
            $table->unsignedBigInteger('matches_lost')->default(0);
            $table->unsignedBigInteger('matches_drawn')->default(0);
            $table->unsignedBigInteger('forfeits')->default(0);
            $table->unsignedBigInteger('rounds_played')->default(0);
            $table->unsignedBigInteger('rounds_won')->default(0);
            $table->unsignedBigInteger('rounds_lost')->default(0);
            $table->unsignedBigInteger('rounds_drawn')->default(0);
            $table->integer('current_streak')->default(0);
            $table->unsignedInteger('best_streak')->default(0);
            $table->decimal('win_rate', 8, 4)->default(0);
            $table->decimal('average_response_ms', 12, 2)->default(0);
            $table->unsignedBigInteger('coins_wagered')->default(0);
            $table->unsignedBigInteger('coins_won')->default(0);
            $table->unsignedBigInteger('diamonds_won')->default(0);
            $table->unsignedBigInteger('tournaments_played')->default(0);
            $table->unsignedBigInteger('tournaments_won')->default(0);
            $table->json('move_distribution')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'config_id'], 'rps_statistics_user_config_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rps_statistics');
        Schema::dropIfExists('rps_histories');
        Schema::dropIfExists('rps_rewards');
        Schema::dropIfExists('rps_brackets');
        Schema::dropIfExists('rps_moves');
        Schema::dropIfExists('rps_rounds');
        Schema::dropIfExists('rps_matches');
        Schema::dropIfExists('rps_tournaments');
        Schema::dropIfExists('rps_configs');
    }
};

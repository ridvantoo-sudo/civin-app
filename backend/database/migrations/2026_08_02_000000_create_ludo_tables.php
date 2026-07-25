<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ludo_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('game_id')->nullable()->index();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('min_entry_fee_coins')->default(0);
            $table->unsignedBigInteger('max_entry_fee_coins')->default(5000);
            $table->unsignedBigInteger('default_entry_fee_coins')->default(10);
            $table->unsignedInteger('ready_timeout_seconds')->default(60);
            $table->unsignedInteger('turn_timeout_seconds')->default(30);
            $table->unsignedInteger('match_timeout_seconds')->default(3600);
            $table->unsignedTinyInteger('forfeit_after_afk')->default(3);
            $table->unsignedInteger('house_rake_bps')->default(0);
            $table->json('modes_enabled');
            $table->json('matchmaking_enabled');
            $table->json('tournament_formats_enabled');
            $table->json('rules');
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false)->index();
            $table->string('status', 24)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ludo_tournaments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('config_id')->nullable()->index();
            $table->uuid('game_id')->nullable()->index();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('format', 32)->default('single_elimination');
            $table->string('status', 24)->default('registration')->index();
            $table->string('mode', 32)->default('classic_4');
            $table->unsignedInteger('max_players')->default(16);
            $table->unsignedInteger('min_players')->default(2);
            $table->unsignedInteger('players_count')->default(0);
            $table->unsignedBigInteger('entry_fee_coins')->default(0);
            $table->unsignedBigInteger('reward_pool')->default(0);
            $table->json('prize_structure')->nullable();
            $table->json('participant_ids')->nullable();
            $table->boolean('is_daily')->default(false);
            $table->boolean('is_scheduled')->default(true);
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ludo_matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('config_id')->nullable()->index();
            $table->uuid('game_id')->nullable()->index();
            $table->uuid('tournament_id')->nullable()->index();
            $table->uuid('host_id')->index();
            $table->uuid('winner_id')->nullable()->index();
            $table->string('invite_code', 16)->nullable()->unique();
            $table->uuid('idempotency_key');
            $table->string('mode', 32)->index();
            $table->string('matchmaking', 32)->index();
            $table->string('status', 24)->index();
            $table->string('result', 24)->nullable();
            $table->unsignedTinyInteger('max_players')->default(4);
            $table->unsignedTinyInteger('players_count')->default(1);
            $table->string('current_turn_color', 10)->nullable();
            $table->uuid('current_player_id')->nullable()->index();
            $table->unsignedInteger('turn_index')->default(0);
            $table->json('board_state')->nullable();
            $table->unsignedTinyInteger('dice_value')->nullable();
            $table->json('rules');
            $table->timestamp('turn_deadline_at')->nullable()->index();
            $table->unsignedBigInteger('pot_coins')->default(0);
            $table->unsignedBigInteger('entry_fee_coins')->default(0);
            $table->unsignedBigInteger('rake_coins')->default(0);
            $table->string('room_id', 64)->nullable()->index();
            $table->string('room_type', 32)->nullable();
            $table->boolean('spectators_enabled')->default(true);
            $table->boolean('replay_enabled')->default(true);
            $table->boolean('is_paused')->default(false);
            $table->uuid('paused_by')->nullable();
            $table->string('pause_reason')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('ready_deadline_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['host_id', 'idempotency_key']);
        });

        Schema::create('ludo_players', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('match_id')->index();
            $table->uuid('user_id')->nullable()->index();
            $table->string('color', 10)->nullable();
            $table->unsignedTinyInteger('seat');
            $table->boolean('is_ready')->default(false);
            $table->boolean('is_host')->default(false);
            $table->boolean('is_bot')->default(false);
            $table->boolean('is_spectator')->default(false);
            $table->boolean('is_connected')->default(true);
            $table->unsignedTinyInteger('afk_count')->default(0);
            $table->unsignedTinyInteger('pieces_finished')->default(0);
            $table->unsignedInteger('captures')->default(0);
            $table->unsignedInteger('reconnect_count')->default(0);
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['match_id', 'user_id']);
            $table->unique(['match_id', 'seat']);
            $table->unique(['match_id', 'color']);
        });

        Schema::create('ludo_turns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('match_id')->index();
            $table->uuid('player_id')->index();
            $table->unsignedInteger('turn_index');
            $table->string('color', 10);
            $table->unsignedTinyInteger('dice_value')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamp('started_at');
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['match_id', 'turn_index']);
        });

        Schema::create('ludo_moves', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('match_id')->index();
            $table->uuid('turn_id')->index();
            $table->uuid('player_id')->index();
            $table->unsignedTinyInteger('piece_index');
            $table->string('from_pos', 40);
            $table->string('to_pos', 40);
            $table->unsignedTinyInteger('dice_value');
            $table->json('captured_piece')->nullable();
            $table->boolean('granted_extra_turn')->default(false);
            $table->uuid('idempotency_key');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['player_id', 'idempotency_key']);
        });

        Schema::create('ludo_pieces', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('match_id')->index();
            $table->uuid('player_id')->index();
            $table->string('color', 10);
            $table->unsignedTinyInteger('piece_index');
            $table->string('position', 40)->default('-1');
            $table->string('status', 16)->default('yard');
            $table->timestamps();
            $table->unique(['match_id', 'color', 'piece_index']);
        });

        Schema::create('ludo_rewards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('match_id')->nullable()->index();
            $table->uuid('tournament_id')->nullable()->index();
            $table->uuid('user_id')->index();
            $table->string('type', 24)->default('coins');
            $table->unsignedBigInteger('amount')->default(0);
            $table->boolean('is_refund')->default(false);
            $table->string('status', 24)->default('granted');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ludo_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('match_id')->index();
            $table->uuid('tournament_id')->nullable()->index();
            $table->string('mode', 32);
            $table->string('result', 24);
            $table->boolean('is_winner')->default(false);
            $table->unsignedInteger('captures')->default(0);
            $table->unsignedTinyInteger('pieces_finished')->default(0);
            $table->unsignedBigInteger('entry_fee_coins')->default(0);
            $table->unsignedBigInteger('reward_coins')->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('ludo_statistics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->unsignedInteger('matches_played')->default(0);
            $table->unsignedInteger('matches_won')->default(0);
            $table->unsignedInteger('matches_lost')->default(0);
            $table->unsignedInteger('matches_drawn')->default(0);
            $table->unsignedInteger('forfeits')->default(0);
            $table->unsignedInteger('captures')->default(0);
            $table->unsignedInteger('pieces_finished')->default(0);
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('best_streak')->default(0);
            $table->unsignedBigInteger('coins_wagered')->default(0);
            $table->unsignedBigInteger('coins_won')->default(0);
            $table->unsignedInteger('tournaments_played')->default(0);
            $table->unsignedInteger('tournaments_won')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ludo_replays', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('match_id')->unique();
            $table->json('events');
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ludo_brackets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tournament_id')->index();
            $table->uuid('match_id')->nullable()->index();
            $table->unsignedInteger('round');
            $table->unsignedInteger('position');
            $table->json('player_ids')->nullable();
            $table->uuid('winner_id')->nullable();
            $table->string('status', 24)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tournament_id', 'round', 'position']);
        });
    }

    public function down(): void
    {
        foreach ([
            'ludo_brackets', 'ludo_replays', 'ludo_statistics', 'ludo_histories',
            'ludo_rewards', 'ludo_pieces', 'ludo_moves', 'ludo_turns',
            'ludo_players', 'ludo_matches', 'ludo_tournaments', 'ludo_configs',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

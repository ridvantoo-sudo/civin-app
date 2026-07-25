<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive Quiz plugin schema.
 * Extends platform quiz_questions and adds dedicated session/reward tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('min_bet_coins')->default(10);
            $table->unsignedInteger('max_bet_coins')->default(5000);
            $table->unsignedInteger('default_bet_coins')->default(10);
            $table->unsignedTinyInteger('default_question_count')->default(10);
            $table->unsignedTinyInteger('max_question_count')->default(50);
            $table->unsignedInteger('default_time_per_question')->default(15);
            $table->unsignedInteger('min_time_per_question')->default(5);
            $table->unsignedInteger('max_time_per_question')->default(120);
            $table->unsignedTinyInteger('default_winner_count')->default(3);
            $table->unsignedTinyInteger('max_winner_count')->default(50);
            $table->unsignedInteger('max_players')->default(100);
            $table->unsignedInteger('max_created_per_day')->nullable();
            $table->unsignedInteger('cooldown_seconds')->default(0);
            $table->json('modes_enabled')->nullable();
            $table->json('question_types_enabled')->nullable();
            $table->json('difficulties_enabled')->nullable();
            $table->json('reward_types_enabled')->nullable();
            $table->json('visibilities_enabled')->nullable();
            $table->json('eligibilities_enabled')->nullable();
            $table->json('scoring_rules')->nullable();
            $table->decimal('vip_reward_multiplier', 5, 2)->default(1.25);
            $table->boolean('enabled')->default(true);
            $table->string('status', 16)->default('active')->index();
            $table->boolean('is_default')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'is_default']);
        });

        Schema::create('quiz_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('icon', 64)->nullable();
            $table->boolean('is_custom')->default(false);
            $table->string('status', 16)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->foreignUuid('category_id')->nullable()->after('id')->constrained('quiz_categories')->nullOnDelete();
            $table->foreignUuid('config_id')->nullable()->after('category_id')->constrained('quiz_configs')->nullOnDelete();
            $table->string('type', 32)->default('multiple_choice')->after('question')->index();
            $table->string('language', 16)->default('en')->after('type')->index();
            $table->unsignedInteger('version')->default(1)->after('language');
            $table->uuid('parent_question_id')->nullable()->after('version');
            $table->string('prompt', 1000)->nullable()->after('parent_question_id');
            $table->string('media_url', 500)->nullable()->after('prompt');
            $table->string('media_type', 32)->nullable()->after('media_url');
            $table->json('translations')->nullable()->after('media_type');
            $table->json('tags')->nullable()->after('translations');
            $table->string('approval_status', 24)->default('approved')->after('status')->index();
            $table->uuid('created_by')->nullable()->after('approval_status');
            $table->uuid('approved_by')->nullable()->after('created_by');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->json('metadata')->nullable()->after('approved_at');
            $table->index(['category_id', 'difficulty', 'status']);
            $table->index(['type', 'language', 'status']);
        });

        Schema::create('quiz_question_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('option_index');
            $table->string('label', 500);
            $table->string('media_url', 500)->nullable();
            $table->boolean('is_correct')->default(false);
            $table->json('translations')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['question_id', 'option_index'], 'quiz_question_options_unique');
            $table->index(['question_id', 'is_correct']);
        });

        Schema::create('quiz_tournaments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('config_id')->nullable()->constrained('quiz_configs')->nullOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->string('slug', 64)->unique();
            $table->string('name', 160);
            $table->string('description', 1000)->nullable();
            $table->string('status', 24)->default('scheduled')->index();
            $table->unsignedTinyInteger('rounds')->default(3);
            $table->unsignedTinyInteger('current_round')->default(0);
            $table->unsignedInteger('max_players')->default(100);
            $table->unsignedInteger('entry_fee_coins')->default(0);
            $table->string('reward_type', 32)->default('coins');
            $table->unsignedInteger('reward_pool')->default(0);
            $table->json('prize_structure')->nullable();
            $table->json('eligibility')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('quiz_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('config_id')->nullable()->constrained('quiz_configs')->nullOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignUuid('host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('tournament_id')->nullable()->constrained('quiz_tournaments')->nullOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('quiz_categories')->nullOnDelete();
            $table->string('idempotency_key', 64);
            $table->string('mode', 32)->default('classic')->index();
            $table->string('difficulty', 16)->default('medium')->index();
            $table->string('status', 24)->default('lobby')->index();
            $table->string('visibility', 32)->default('public')->index();
            $table->string('eligibility', 32)->default('everyone')->index();
            $table->string('reward_type', 32)->default('coins');
            $table->unsignedInteger('reward_pool')->default(0);
            $table->unsignedInteger('bet_coins')->default(0);
            $table->unsignedTinyInteger('question_count')->default(10);
            $table->unsignedInteger('time_per_question')->default(15);
            $table->unsignedTinyInteger('winner_count')->default(3);
            $table->unsignedTinyInteger('current_round')->default(0);
            $table->unsignedTinyInteger('current_question_index')->default(0);
            $table->uuid('current_question_id')->nullable();
            $table->string('room_id', 64)->nullable()->index();
            $table->string('room_type', 32)->nullable()->index();
            $table->string('language', 16)->default('en');
            $table->unsignedInteger('player_count')->default(0);
            $table->unsignedInteger('config_version')->default(1);
            $table->json('question_ids')->nullable();
            $table->json('scoring_rules')->nullable();
            $table->json('whitelist_user_ids')->nullable();
            $table->json('team_settings')->nullable();
            $table->json('audit_log')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('question_started_at')->nullable();
            $table->timestamp('question_ends_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['host_id', 'idempotency_key'], 'quiz_sessions_idempotency_unique');
            $table->index(['status', 'mode', 'created_at']);
            $table->index(['tournament_id', 'status']);
        });

        Schema::create('quiz_players', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('quiz_sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('idempotency_key', 64)->nullable();
            $table->string('status', 24)->default('joined')->index();
            $table->string('team_id', 64)->nullable()->index();
            $table->string('team_name', 120)->nullable();
            $table->boolean('is_host')->default(false);
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('wrong_count')->default(0);
            $table->unsignedInteger('streak')->default(0);
            $table->unsignedInteger('best_streak')->default(0);
            $table->unsignedInteger('total_response_ms')->default(0);
            $table->unsignedInteger('answers_count')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->boolean('is_winner')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'user_id'], 'quiz_players_session_user_unique');
            $table->unique(['user_id', 'idempotency_key'], 'quiz_players_idempotency_unique');
            $table->index(['session_id', 'score']);
        });

        Schema::create('quiz_answers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('quiz_sessions')->cascadeOnDelete();
            $table->foreignUuid('player_id')->constrained('quiz_players')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->string('idempotency_key', 64);
            $table->unsignedTinyInteger('round_index')->default(0);
            $table->unsignedTinyInteger('question_index')->default(0);
            $table->integer('selected_index')->nullable();
            $table->string('selected_text', 500)->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('points_awarded')->default(0);
            $table->unsignedInteger('speed_bonus')->default(0);
            $table->unsignedInteger('streak_bonus')->default(0);
            $table->unsignedInteger('response_ms')->nullable();
            $table->boolean('timed_out')->default(false);
            $table->string('server_seed', 64)->nullable();
            $table->json('audit')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('answered_at')->useCurrent();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'quiz_answers_idempotency_unique');
            $table->unique(['session_id', 'user_id', 'question_id'], 'quiz_answers_once_per_question');
            $table->index(['session_id', 'question_id']);
            $table->index(['player_id', 'answered_at']);
        });

        Schema::create('quiz_scores', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('quiz_sessions')->cascadeOnDelete();
            $table->foreignUuid('player_id')->constrained('quiz_players')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('team_id', 64)->nullable()->index();
            $table->unsignedInteger('total_score')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('wrong_count')->default(0);
            $table->unsignedInteger('speed_bonus_total')->default(0);
            $table->unsignedInteger('streak_bonus_total')->default(0);
            $table->unsignedInteger('perfect_round_bonus')->default(0);
            $table->unsignedInteger('tournament_bonus')->default(0);
            $table->unsignedInteger('vip_bonus')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->boolean('is_winner')->default(false);
            $table->json('breakdown')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'user_id'], 'quiz_scores_session_user_unique');
            $table->index(['session_id', 'total_score']);
        });

        Schema::create('quiz_rewards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('quiz_sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('player_id')->nullable()->constrained('quiz_players')->nullOnDelete();
            $table->foreignUuid('score_id')->nullable()->constrained('quiz_scores')->nullOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('quiz_configs')->nullOnDelete();
            $table->string('reward_type', 32)->default('coins')->index();
            $table->unsignedInteger('amount')->default(0);
            $table->string('currency', 16)->nullable();
            $table->string('reference', 64)->nullable();
            $table->unsignedTinyInteger('rank')->nullable();
            $table->boolean('is_jackpot')->default(false);
            $table->boolean('is_mystery')->default(false);
            $table->boolean('is_vip')->default(false);
            $table->string('status', 24)->default('granted')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['session_id', 'status']);
        });

        Schema::create('quiz_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('session_id')->nullable()->constrained('quiz_sessions')->nullOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('quiz_configs')->nullOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignUuid('play_id')->nullable()->constrained('game_plays')->nullOnDelete();
            $table->string('mode', 32)->nullable();
            $table->string('result', 16)->default('finished');
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->unsignedInteger('bet_coins')->default(0);
            $table->unsignedInteger('reward_coins')->default(0);
            $table->unsignedInteger('reward_diamonds')->default(0);
            $table->boolean('is_winner')->default(false);
            $table->json('snapshot')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('quiz_statistics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('config_id')->nullable()->constrained('quiz_configs')->nullOnDelete();
            $table->unsignedBigInteger('games_played')->default(0);
            $table->unsignedBigInteger('questions_answered')->default(0);
            $table->unsignedBigInteger('correct_answers')->default(0);
            $table->unsignedBigInteger('wrong_answers')->default(0);
            $table->decimal('accuracy_percent', 8, 4)->default(0);
            $table->decimal('average_response_ms', 12, 2)->default(0);
            $table->unsignedBigInteger('highest_score')->default(0);
            $table->unsignedBigInteger('total_score')->default(0);
            $table->unsignedBigInteger('wins')->default(0);
            $table->decimal('win_rate', 8, 4)->default(0);
            $table->unsignedBigInteger('coins_won')->default(0);
            $table->unsignedBigInteger('diamonds_won')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'config_id'], 'quiz_statistics_user_config_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_statistics');
        Schema::dropIfExists('quiz_histories');
        Schema::dropIfExists('quiz_rewards');
        Schema::dropIfExists('quiz_scores');
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_players');
        Schema::dropIfExists('quiz_sessions');
        Schema::dropIfExists('quiz_tournaments');
        Schema::dropIfExists('quiz_question_options');

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['config_id']);
            $table->dropIndex(['category_id', 'difficulty', 'status']);
            $table->dropIndex(['type', 'language', 'status']);
            $table->dropColumn([
                'category_id',
                'config_id',
                'type',
                'language',
                'version',
                'parent_question_id',
                'prompt',
                'media_url',
                'media_type',
                'translations',
                'tags',
                'approval_status',
                'created_by',
                'approved_by',
                'approved_at',
                'metadata',
            ]);
        });

        Schema::dropIfExists('quiz_categories');
        Schema::dropIfExists('quiz_configs');
    }
};

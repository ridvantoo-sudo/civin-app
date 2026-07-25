<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enrich the existing Games append-only log without breaking writers.
        if (Schema::hasTable('game_analytics_events')) {
            Schema::table('game_analytics_events', function (Blueprint $table): void {
                if (! Schema::hasColumn('game_analytics_events', 'idempotency_key')) {
                    $table->string('idempotency_key', 191)->nullable()->after('id');
                }
                if (! Schema::hasColumn('game_analytics_events', 'source')) {
                    $table->string('source', 64)->nullable()->index()->after('event');
                }
                if (! Schema::hasColumn('game_analytics_events', 'category')) {
                    $table->string('category', 64)->nullable()->index()->after('source');
                }
                if (! Schema::hasColumn('game_analytics_events', 'game_slug')) {
                    $table->string('game_slug', 64)->nullable()->index()->after('game_id');
                }
                if (! Schema::hasColumn('game_analytics_events', 'room_type')) {
                    $table->string('room_type', 32)->nullable()->index()->after('game_slug');
                }
                if (! Schema::hasColumn('game_analytics_events', 'room_id')) {
                    $table->uuid('room_id')->nullable()->index()->after('room_type');
                }
                if (! Schema::hasColumn('game_analytics_events', 'country_code')) {
                    $table->string('country_code', 8)->nullable()->index()->after('room_id');
                }
                if (! Schema::hasColumn('game_analytics_events', 'device_platform')) {
                    $table->string('device_platform', 32)->nullable()->index()->after('country_code');
                }
                if (! Schema::hasColumn('game_analytics_events', 'coins_wagered')) {
                    $table->decimal('coins_wagered', 18, 4)->default(0)->after('device_platform');
                }
                if (! Schema::hasColumn('game_analytics_events', 'coins_won')) {
                    $table->decimal('coins_won', 18, 4)->default(0)->after('coins_wagered');
                }
                if (! Schema::hasColumn('game_analytics_events', 'coins_burned')) {
                    $table->decimal('coins_burned', 18, 4)->default(0)->after('coins_won');
                }
                if (! Schema::hasColumn('game_analytics_events', 'diamonds_consumed')) {
                    $table->decimal('diamonds_consumed', 18, 4)->default(0)->after('coins_burned');
                }
                if (! Schema::hasColumn('game_analytics_events', 'revenue')) {
                    $table->decimal('revenue', 18, 4)->default(0)->after('diamonds_consumed');
                }
                if (! Schema::hasColumn('game_analytics_events', 'metadata')) {
                    $table->json('metadata')->nullable()->after('payload');
                }
                if (! Schema::hasColumn('game_analytics_events', 'occurred_at')) {
                    $table->timestamp('occurred_at')->nullable()->index()->after('created_at');
                }
            });

            try {
                Schema::table('game_analytics_events', function (Blueprint $table): void {
                    $table->unique('idempotency_key', 'ga_events_idempotency_unique');
                    $table->index(['source', 'occurred_at'], 'ga_events_source_occurred_idx');
                    $table->index(['game_slug', 'occurred_at'], 'ga_events_slug_occurred_idx');
                    $table->index(['country_code', 'occurred_at'], 'ga_events_country_occurred_idx');
                });
            } catch (Throwable) {
                // Indexes may already exist on re-runs / alternate drivers.
            }
        } else {
            Schema::create('game_analytics_events', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('idempotency_key', 191)->nullable()->unique('ga_events_idempotency_unique');
                $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
                $table->string('game_slug', 64)->nullable()->index();
                $table->string('event', 64)->index();
                $table->string('source', 64)->nullable()->index();
                $table->string('category', 64)->nullable()->index();
                $table->string('room_type', 32)->nullable()->index();
                $table->uuid('room_id')->nullable()->index();
                $table->string('country_code', 8)->nullable()->index();
                $table->string('device_platform', 32)->nullable()->index();
                $table->decimal('coins_wagered', 18, 4)->default(0);
                $table->decimal('coins_won', 18, 4)->default(0);
                $table->decimal('coins_burned', 18, 4)->default(0);
                $table->decimal('diamonds_consumed', 18, 4)->default(0);
                $table->decimal('revenue', 18, 4)->default(0);
                $table->json('payload')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('occurred_at')->nullable()->index();
                $table->index(['game_id', 'event', 'created_at']);
                $table->index(['user_id', 'created_at']);
                $table->index(['source', 'occurred_at'], 'ga_events_source_occurred_idx');
                $table->index(['game_slug', 'occurred_at'], 'ga_events_slug_occurred_idx');
                $table->index(['country_code', 'occurred_at'], 'ga_events_country_occurred_idx');
            });
        }

        Schema::create('game_analytics_hourly', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamp('hour_start');
            $table->string('game_slug', 64)->nullable();
            $table->string('country_code', 8)->nullable();
            $table->string('device_platform', 32)->nullable();
            $table->unsignedBigInteger('games_played')->default(0);
            $table->unsignedBigInteger('unique_players')->default(0);
            $table->unsignedBigInteger('matches_started')->default(0);
            $table->unsignedBigInteger('matches_finished')->default(0);
            $table->unsignedBigInteger('reconnect_count')->default(0);
            $table->unsignedBigInteger('abandoned_games')->default(0);
            $table->decimal('coins_wagered', 18, 4)->default(0);
            $table->decimal('coins_won', 18, 4)->default(0);
            $table->decimal('coins_burned', 18, 4)->default(0);
            $table->decimal('diamonds_consumed', 18, 4)->default(0);
            $table->decimal('revenue', 18, 4)->default(0);
            $table->decimal('profit', 18, 4)->default(0);
            $table->decimal('avg_duration_seconds', 12, 2)->default(0);
            $table->decimal('avg_bet', 18, 4)->default(0);
            $table->decimal('avg_reward', 18, 4)->default(0);
            $table->decimal('rtp', 8, 4)->default(0);
            $table->decimal('house_edge', 8, 4)->default(0);
            $table->unsignedBigInteger('jackpots')->default(0);
            $table->unsignedBigInteger('bonus_wins')->default(0);
            $table->unsignedBigInteger('free_plays')->default(0);
            $table->unsignedInteger('peak_ccu')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['hour_start', 'game_slug', 'country_code', 'device_platform'], 'ga_hourly_dim_unique');
            $table->index(['hour_start', 'revenue'], 'ga_hourly_revenue_idx');
            $table->index(['game_slug', 'hour_start'], 'ga_hourly_game_idx');
        });

        Schema::create('game_analytics_daily', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('stat_date');
            $table->string('game_slug', 64)->nullable();
            $table->string('country_code', 8)->nullable();
            $table->string('device_platform', 32)->nullable();
            $table->unsignedBigInteger('games_played')->default(0);
            $table->unsignedBigInteger('unique_players')->default(0);
            $table->unsignedBigInteger('returning_players')->default(0);
            $table->unsignedBigInteger('matches_started')->default(0);
            $table->unsignedBigInteger('matches_finished')->default(0);
            $table->unsignedBigInteger('reconnect_count')->default(0);
            $table->unsignedBigInteger('abandoned_games')->default(0);
            $table->unsignedBigInteger('wins')->default(0);
            $table->unsignedBigInteger('losses')->default(0);
            $table->decimal('coins_wagered', 18, 4)->default(0);
            $table->decimal('coins_won', 18, 4)->default(0);
            $table->decimal('coins_burned', 18, 4)->default(0);
            $table->decimal('diamonds_consumed', 18, 4)->default(0);
            $table->decimal('revenue', 18, 4)->default(0);
            $table->decimal('profit', 18, 4)->default(0);
            $table->decimal('avg_duration_seconds', 12, 2)->default(0);
            $table->decimal('avg_session_length', 12, 2)->default(0);
            $table->decimal('avg_bet', 18, 4)->default(0);
            $table->decimal('avg_reward', 18, 4)->default(0);
            $table->decimal('rtp', 8, 4)->default(0);
            $table->decimal('house_edge', 8, 4)->default(0);
            $table->decimal('win_rate', 8, 4)->default(0);
            $table->decimal('loss_rate', 8, 4)->default(0);
            $table->unsignedBigInteger('jackpots')->default(0);
            $table->unsignedBigInteger('bonus_wins')->default(0);
            $table->unsignedBigInteger('free_plays')->default(0);
            $table->unsignedInteger('dau')->default(0);
            $table->unsignedInteger('peak_ccu')->default(0);
            $table->decimal('arpu', 18, 4)->default(0);
            $table->decimal('arppu', 18, 4)->default(0);
            $table->decimal('conversion_rate', 8, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['stat_date', 'game_slug', 'country_code', 'device_platform'], 'ga_daily_dim_unique');
            $table->index(['stat_date', 'revenue'], 'ga_daily_revenue_idx');
            $table->index(['game_slug', 'stat_date'], 'ga_daily_game_idx');
        });

        Schema::create('game_analytics_monthly', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('period_key', 7); // YYYY-MM
            $table->string('game_slug', 64)->nullable();
            $table->string('country_code', 8)->nullable();
            $table->string('device_platform', 32)->nullable();
            $table->unsignedBigInteger('games_played')->default(0);
            $table->unsignedBigInteger('unique_players')->default(0);
            $table->unsignedBigInteger('returning_players')->default(0);
            $table->unsignedBigInteger('matches_started')->default(0);
            $table->unsignedBigInteger('matches_finished')->default(0);
            $table->decimal('coins_wagered', 18, 4)->default(0);
            $table->decimal('coins_won', 18, 4)->default(0);
            $table->decimal('coins_burned', 18, 4)->default(0);
            $table->decimal('diamonds_consumed', 18, 4)->default(0);
            $table->decimal('revenue', 18, 4)->default(0);
            $table->decimal('profit', 18, 4)->default(0);
            $table->decimal('rtp', 8, 4)->default(0);
            $table->decimal('house_edge', 8, 4)->default(0);
            $table->unsignedInteger('mau')->default(0);
            $table->unsignedInteger('wau')->default(0);
            $table->decimal('arpu', 18, 4)->default(0);
            $table->decimal('arppu', 18, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['period_key', 'game_slug', 'country_code', 'device_platform'], 'ga_monthly_dim_unique');
            $table->index(['period_key', 'revenue'], 'ga_monthly_revenue_idx');
        });

        Schema::create('game_player_statistics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('game_slug', 64)->nullable()->index();
            $table->date('stat_date')->nullable()->index();
            $table->unsignedBigInteger('games_played')->default(0);
            $table->unsignedBigInteger('wins')->default(0);
            $table->unsignedBigInteger('losses')->default(0);
            $table->unsignedBigInteger('reconnect_count')->default(0);
            $table->decimal('coins_wagered', 18, 4)->default(0);
            $table->decimal('coins_won', 18, 4)->default(0);
            $table->decimal('revenue_contributed', 18, 4)->default(0);
            $table->decimal('avg_session_length', 12, 2)->default(0);
            $table->timestamp('first_played_at')->nullable();
            $table->timestamp('last_played_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'game_slug', 'stat_date'], 'ga_player_dim_unique');
            $table->index(['stat_date', 'games_played'], 'ga_player_activity_idx');
        });

        Schema::create('game_room_statistics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('room_type', 32)->index();
            $table->uuid('room_id')->index();
            $table->foreignUuid('host_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('stat_date')->index();
            $table->unsignedBigInteger('sessions')->default(0);
            $table->unsignedBigInteger('unique_players')->default(0);
            $table->unsignedInteger('peak_ccu')->default(0);
            $table->decimal('coins_wagered', 18, 4)->default(0);
            $table->decimal('revenue', 18, 4)->default(0);
            $table->decimal('gifts_volume', 18, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['room_type', 'room_id', 'stat_date'], 'ga_room_dim_unique');
            $table->index(['stat_date', 'revenue'], 'ga_room_revenue_idx');
            $table->index(['host_id', 'stat_date'], 'ga_room_host_idx');
        });

        Schema::create('game_revenue_statistics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('stat_date')->index();
            $table->string('game_slug', 64)->nullable()->index();
            $table->string('source', 64)->nullable()->index();
            $table->decimal('gross_revenue', 18, 4)->default(0);
            $table->decimal('net_revenue', 18, 4)->default(0);
            $table->decimal('coins_wagered', 18, 4)->default(0);
            $table->decimal('coins_won', 18, 4)->default(0);
            $table->decimal('coins_burned', 18, 4)->default(0);
            $table->decimal('diamonds_consumed', 18, 4)->default(0);
            $table->decimal('gift_revenue', 18, 4)->default(0);
            $table->decimal('wallet_revenue', 18, 4)->default(0);
            $table->decimal('vip_revenue', 18, 4)->default(0);
            $table->decimal('agency_commission', 18, 4)->default(0);
            $table->unsignedBigInteger('paying_users')->default(0);
            $table->decimal('arpu', 18, 4)->default(0);
            $table->decimal('arppu', 18, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['stat_date', 'game_slug', 'source'], 'ga_revenue_dim_unique');
            $table->index(['stat_date', 'gross_revenue'], 'ga_revenue_gross_idx');
        });

        Schema::create('game_retention_statistics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('cohort_date')->index();
            $table->string('cohort_type', 32)->default('daily')->index(); // daily|weekly|monthly|vip|agency|country|platform
            $table->string('cohort_key', 64)->nullable()->index();
            $table->unsignedInteger('cohort_size')->default(0);
            $table->unsignedInteger('retained_d1')->default(0);
            $table->unsignedInteger('retained_d7')->default(0);
            $table->unsignedInteger('retained_d30')->default(0);
            $table->decimal('retention_d1_rate', 8, 4)->default(0);
            $table->decimal('retention_d7_rate', 8, 4)->default(0);
            $table->decimal('retention_d30_rate', 8, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['cohort_date', 'cohort_type', 'cohort_key'], 'ga_retention_dim_unique');
        });

        Schema::create('game_country_statistics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('stat_date')->index();
            $table->string('country_code', 8)->index();
            $table->unsignedBigInteger('dau')->default(0);
            $table->unsignedBigInteger('games_played')->default(0);
            $table->decimal('revenue', 18, 4)->default(0);
            $table->decimal('coins_wagered', 18, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['stat_date', 'country_code'], 'ga_country_dim_unique');
            $table->index(['stat_date', 'revenue'], 'ga_country_revenue_idx');
        });

        Schema::create('game_device_statistics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('stat_date')->index();
            $table->string('device_platform', 32)->index();
            $table->unsignedBigInteger('dau')->default(0);
            $table->unsignedBigInteger('games_played')->default(0);
            $table->decimal('revenue', 18, 4)->default(0);
            $table->decimal('coins_wagered', 18, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['stat_date', 'device_platform'], 'ga_device_dim_unique');
            $table->index(['stat_date', 'dau'], 'ga_device_dau_idx');
        });

        Schema::create('game_analytics_scheduled_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('report_type', 64)->index();
            $table->string('frequency', 32)->default('daily')->index();
            $table->json('recipients');
            $table->json('filters')->nullable();
            $table->json('formats')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('game_analytics_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('snapshot_type', 64)->index();
            $table->string('period_key', 32)->nullable()->index();
            $table->json('payload');
            $table->timestamp('captured_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['snapshot_type', 'captured_at'], 'ga_snapshot_type_captured_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_analytics_snapshots');
        Schema::dropIfExists('game_analytics_scheduled_reports');
        Schema::dropIfExists('game_device_statistics');
        Schema::dropIfExists('game_country_statistics');
        Schema::dropIfExists('game_retention_statistics');
        Schema::dropIfExists('game_revenue_statistics');
        Schema::dropIfExists('game_room_statistics');
        Schema::dropIfExists('game_player_statistics');
        Schema::dropIfExists('game_analytics_monthly');
        Schema::dropIfExists('game_analytics_daily');
        Schema::dropIfExists('game_analytics_hourly');

        // Do not drop game_analytics_events — owned/shared with Games feature.
    }
};

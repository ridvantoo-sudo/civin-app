<?php

namespace App\Features\Games\Plugins\LuckyNumber\Services;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberDrawStarted;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberConfig;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberDraw;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use App\Features\Games\Plugins\LuckyNumber\Repositories\Contracts\LuckyNumberRepository;
use App\Features\Games\Plugins\LuckyNumber\Support\LuckyNumberDrawEngine;
use App\Features\Games\Services\GameRng;
use App\Features\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class LuckyNumberDrawService
{
    public function __construct(
        private LuckyNumberRepository $luckyNumbers,
        private LuckyNumberDrawEngine $engine,
        /** Reserved for future seeded RNG wiring; engine currently owns GameRng. */
        private GameRng $rng,
    ) {}

    public function createInstantDraw(
        LuckyNumberConfig $config,
        string $gameMode,
        int $min,
        int $max,
        int $drawCount,
        ?User $triggeredBy = null,
    ): LuckyNumberDraw {
        $game = Game::query()->where('slug', Game::SLUG_LUCKY_NUMBER)->first();

        return $this->luckyNumbers->createDraw([
            'config_id' => $config->getKey(),
            'game_id' => $game?->getKey(),
            'draw_type' => LuckyNumberDraw::TYPE_INSTANT,
            'game_mode' => $gameMode,
            'min_number' => $min,
            'max_number' => $max,
            'draw_count' => max(1, $drawCount),
            'status' => LuckyNumberDraw::STATUS_PENDING,
            'server_seed' => bin2hex(random_bytes(16)),
            'winning_numbers' => null,
            'audit_log' => null,
            'metadata' => null,
            'scheduled_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'triggered_by_user_id' => $triggeredBy?->getKey(),
        ]);
    }

    public function runDraw(
        LuckyNumberDraw $draw,
        ?LuckyNumberTicket $ticket = null,
        ?User $user = null,
    ): LuckyNumberDraw {
        return DB::transaction(function () use ($draw, $ticket, $user): LuckyNumberDraw {
            $locked = LuckyNumberDraw::query()
                ->whereKey($draw->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw ValidationException::withMessages(['draw' => 'Draw not found.']);
            }

            if ($locked->status === LuckyNumberDraw::STATUS_FINISHED) {
                return $locked;
            }

            if ($locked->status === LuckyNumberDraw::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['draw' => 'Draw has been cancelled.']);
            }

            $serverSeed = $locked->server_seed ?: bin2hex(random_bytes(16));

            $locked->forceFill([
                'status' => LuckyNumberDraw::STATUS_STARTED,
                'server_seed' => $serverSeed,
                'started_at' => $locked->started_at ?? now(),
            ])->save();

            LuckyNumberDrawStarted::dispatch($locked, $ticket, $user);

            $drawn = $this->engine->drawNumbers(
                (int) $locked->min_number,
                (int) $locked->max_number,
                (int) $locked->draw_count,
                $serverSeed,
            );

            // Keep rolls server-side only; client-facing audit uses seed hash.
            $audit = array_merge($drawn['audit'], [
                'rolls' => $drawn['rolls'],
                'draw_type' => $locked->draw_type,
                'game_mode' => $locked->game_mode,
            ]);

            $locked->forceFill([
                'status' => LuckyNumberDraw::STATUS_FINISHED,
                'winning_numbers' => $drawn['winning_numbers'],
                'audit_log' => $audit,
                'finished_at' => now(),
            ])->save();

            // LuckyNumberDrawFinished is ticket-scoped; callers with a ticket dispatch it after settlement.

            return $locked->fresh() ?? $locked;
        });
    }

    public function processDueScheduledDraws(int $limit = 50): int
    {
        $draws = $this->luckyNumbers->findPendingScheduledDraws($limit);
        $processed = 0;

        foreach ($draws as $draw) {
            /** @var LuckyNumberDraw $draw */
            $this->runDraw($draw);
            $processed++;
        }

        return $processed;
    }

    public function scheduleNext(LuckyNumberConfig $config): ?LuckyNumberDraw
    {
        if (! $config->isActive()) {
            return null;
        }

        $interval = max(30, (int) $config->draw_interval_seconds);
        $range = $config->rangeForMode(LuckyNumberConfig::MODE_SINGLE_NUMBER);
        $game = Game::query()->where('slug', Game::SLUG_LUCKY_NUMBER)->first();

        return $this->luckyNumbers->createDraw([
            'config_id' => $config->getKey(),
            'game_id' => $game?->getKey(),
            'draw_type' => LuckyNumberDraw::TYPE_SCHEDULED,
            'game_mode' => LuckyNumberConfig::MODE_SINGLE_NUMBER,
            'min_number' => $range['min'],
            'max_number' => $range['max'],
            'draw_count' => max(1, (int) $config->draw_count),
            'status' => LuckyNumberDraw::STATUS_PENDING,
            'server_seed' => bin2hex(random_bytes(16)),
            'winning_numbers' => null,
            'audit_log' => null,
            'metadata' => null,
            'scheduled_at' => now()->addSeconds($interval),
            'started_at' => null,
            'finished_at' => null,
            'triggered_by_user_id' => null,
        ]);
    }

    public function manualDraw(
        LuckyNumberConfig $config,
        User $admin,
        ?string $gameMode = null,
    ): LuckyNumberDraw {
        $mode = $gameMode ?? LuckyNumberConfig::MODE_SINGLE_NUMBER;
        if (! in_array($mode, LuckyNumberConfig::MODES, true)) {
            throw ValidationException::withMessages(['game_mode' => 'Invalid game mode.']);
        }

        $range = $config->rangeForMode($mode);
        $game = Game::query()->where('slug', Game::SLUG_LUCKY_NUMBER)->first();

        $draw = $this->luckyNumbers->createDraw([
            'config_id' => $config->getKey(),
            'game_id' => $game?->getKey(),
            'draw_type' => LuckyNumberDraw::TYPE_MANUAL,
            'game_mode' => $mode,
            'min_number' => $range['min'],
            'max_number' => $range['max'],
            'draw_count' => max(1, (int) $config->draw_count),
            'status' => LuckyNumberDraw::STATUS_PENDING,
            'server_seed' => bin2hex(random_bytes(16)),
            'winning_numbers' => null,
            'audit_log' => null,
            'metadata' => ['manual' => true],
            'scheduled_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'triggered_by_user_id' => $admin->getKey(),
        ]);

        return $this->runDraw($draw);
    }
}

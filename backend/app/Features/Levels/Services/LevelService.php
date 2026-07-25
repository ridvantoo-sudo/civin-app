<?php

namespace App\Features\Levels\Services;

use App\Features\Levels\DTOs\XpAwardResult;
use App\Features\Levels\Events\BadgeUnlocked;
use App\Features\Levels\Events\LevelUp;
use App\Features\Levels\Events\XpAwarded;
use App\Features\Levels\Models\Badge;
use App\Features\Levels\Models\DailyTask;
use App\Features\Levels\Models\UserBadge;
use App\Features\Levels\Models\UserDailyTaskProgress;
use App\Features\Levels\Models\UserLevelProgress;
use App\Features\Levels\Models\XpTransaction;
use App\Features\Levels\Support\XpCurve;
use App\Features\Profiles\Models\Profile;
use App\Features\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class LevelService
{
    public function ensureProgress(User $user): UserLevelProgress
    {
        return UserLevelProgress::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            [
                'level' => max(1, (int) ($user->profile?->level ?? 1)),
                'xp_total' => 0,
                'xp_into_level' => 0,
            ],
        );
    }

    public function progressFor(User $user): UserLevelProgress
    {
        return $this->ensureProgress($user)->fresh() ?? $this->ensureProgress($user);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function award(
        User $user,
        string $source,
        int $amount,
        string $idempotencyKey,
        array $metadata = [],
        ?int $dailyCap = null,
    ): XpAwardResult {
        if (! in_array($source, XpTransaction::SOURCES, true)) {
            throw ValidationException::withMessages(['source' => "Unsupported XP source [{$source}]."]);
        }

        $amount = max(0, $amount);
        if ($amount === 0) {
            $progress = $this->ensureProgress($user);

            return new XpAwardResult($progress, null, false, false, 0, 0);
        }

        return DB::transaction(function () use ($user, $source, $amount, $idempotencyKey, $metadata, $dailyCap): XpAwardResult {
            $existing = XpTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $progress = $this->ensureProgress($user);

                return new XpAwardResult($progress, $existing, false, false, 0, 0);
            }

            $progress = UserLevelProgress::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($progress === null) {
                $progress = UserLevelProgress::query()->create([
                    'user_id' => $user->getKey(),
                    'level' => max(1, (int) (Profile::query()->where('user_id', $user->getKey())->value('level') ?? 1)),
                    'xp_total' => 0,
                    'xp_into_level' => 0,
                ]);
                $progress = UserLevelProgress::query()
                    ->whereKey($progress->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $awardAmount = $amount;
            if ($dailyCap !== null) {
                $awardedToday = (int) XpTransaction::query()
                    ->where('user_id', $user->getKey())
                    ->where('source', $source)
                    ->where('created_at', '>=', now()->startOfDay())
                    ->sum('amount');
                $remaining = max(0, $dailyCap - $awardedToday);
                $awardAmount = min($awardAmount, $remaining);
            }

            if ($awardAmount <= 0) {
                return new XpAwardResult($progress, null, false, false, 0, 0);
            }

            $levelBefore = (int) $progress->level;
            $xpTotal = (int) $progress->xp_total + $awardAmount;
            $resolved = XpCurve::resolveFromTotalXp($xpTotal);
            $levelAfter = $resolved['level'];
            $leveledUp = $levelAfter > $levelBefore;

            $progress->update([
                'level' => $levelAfter,
                'xp_total' => $xpTotal,
                'xp_into_level' => $resolved['xp_into_level'],
            ]);

            Profile::query()
                ->where('user_id', $user->getKey())
                ->update(['level' => $levelAfter]);

            $transaction = XpTransaction::query()->create([
                'user_id' => $user->getKey(),
                'source' => $source,
                'amount' => $awardAmount,
                'level_before' => $levelBefore,
                'level_after' => $levelAfter,
                'xp_total_after' => $xpTotal,
                'idempotency_key' => $idempotencyKey,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);

            DB::afterCommit(function () use ($transaction, $user, $levelBefore, $levelAfter, $xpTotal, $leveledUp, $source, $awardAmount): void {
                event(new XpAwarded($transaction));

                if ($leveledUp) {
                    event(new LevelUp(
                        userId: (string) $user->getKey(),
                        fromLevel: $levelBefore,
                        toLevel: $levelAfter,
                        xpTotal: $xpTotal,
                    ));
                }

                $this->trackDailyTaskProgress($user, $source, $awardAmount, $transaction->metadata ?? []);
                $this->evaluateBadges($user);
            });

            return new XpAwardResult(
                progress: $progress->fresh() ?? $progress,
                transaction: $transaction,
                awarded: true,
                leveledUp: $leveledUp,
                levelsGained: max(0, $levelAfter - $levelBefore),
                amount: $awardAmount,
            );
        });
    }

    public function awardLogin(User $user): XpAwardResult
    {
        $xp = (int) config('levels.sources.login.xp', 50);

        return $this->award(
            user: $user,
            source: XpTransaction::SOURCE_LOGIN,
            amount: $xp,
            idempotencyKey: 'login:'.$user->getKey().':'.now()->toDateString(),
            metadata: ['date' => now()->toDateString()],
        );
    }

    public function awardLive(User $host, string $roomId, int $durationSeconds): XpAwardResult
    {
        $config = config('levels.sources.live', []);
        $minSeconds = (int) ($config['min_seconds'] ?? 60);
        if ($durationSeconds < $minSeconds) {
            return new XpAwardResult($this->ensureProgress($host), null, false, false, 0, 0);
        }

        $minutes = intdiv($durationSeconds, 60);
        $amount = $minutes * (int) ($config['xp_per_minute'] ?? 10);

        return $this->award(
            user: $host,
            source: XpTransaction::SOURCE_LIVE,
            amount: $amount,
            idempotencyKey: 'live:'.$roomId,
            metadata: [
                'room_id' => $roomId,
                'duration_seconds' => $durationSeconds,
                'minutes' => $minutes,
            ],
            dailyCap: (int) ($config['daily_cap'] ?? 2000),
        );
    }

    public function awardWatching(
        User $viewer,
        string $roomId,
        int $watchSeconds,
        string $joinedAtKey,
    ): XpAwardResult {
        $config = config('levels.sources.watching', []);
        $minSeconds = (int) ($config['min_seconds'] ?? 60);
        if ($watchSeconds < $minSeconds) {
            return new XpAwardResult($this->ensureProgress($viewer), null, false, false, 0, 0);
        }

        $minutes = intdiv($watchSeconds, 60);
        $amount = $minutes * (int) ($config['xp_per_minute'] ?? 2);

        return $this->award(
            user: $viewer,
            source: XpTransaction::SOURCE_WATCHING,
            amount: $amount,
            idempotencyKey: 'watching:'.$roomId.':'.$viewer->getKey().':'.$joinedAtKey,
            metadata: [
                'room_id' => $roomId,
                'watch_seconds' => $watchSeconds,
                'minutes' => $minutes,
            ],
            dailyCap: (int) ($config['daily_cap'] ?? 500),
        );
    }

    public function awardGiftSend(User $sender, string $transactionId, int $coins): XpAwardResult
    {
        $config = config('levels.sources.gift_send', []);
        $rate = (float) ($config['xp_per_coin'] ?? 1);
        $amount = (int) floor($coins * $rate);

        return $this->award(
            user: $sender,
            source: XpTransaction::SOURCE_GIFT_SEND,
            amount: $amount,
            idempotencyKey: 'gift_send:'.$transactionId,
            metadata: [
                'gift_transaction_id' => $transactionId,
                'coins' => $coins,
            ],
            dailyCap: (int) ($config['daily_cap'] ?? 5000),
        );
    }

    public function awardGiftReceive(User $receiver, string $transactionId, int $coins): XpAwardResult
    {
        $config = config('levels.sources.gift_receive', []);
        $rate = (float) ($config['xp_per_coin'] ?? 0.5);
        $amount = (int) floor($coins * $rate);

        return $this->award(
            user: $receiver,
            source: XpTransaction::SOURCE_GIFT_RECEIVE,
            amount: $amount,
            idempotencyKey: 'gift_receive:'.$transactionId,
            metadata: [
                'gift_transaction_id' => $transactionId,
                'coins' => $coins,
            ],
            dailyCap: (int) ($config['daily_cap'] ?? 5000),
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listDailyTasks(User $user): Collection
    {
        $today = now()->toDateString();
        $tasks = DailyTask::query()
            ->where('status', DailyTask::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $progress = UserDailyTaskProgress::query()
            ->where('user_id', $user->getKey())
            ->whereDate('task_date', $today)
            ->get()
            ->keyBy(fn (UserDailyTaskProgress $row): string => (string) $row->daily_task_id);

        return $tasks->map(function (DailyTask $task) use ($progress, $today): array {
            $row = $progress->get((string) $task->getKey());
            $current = (int) ($row?->progress ?? 0);
            $target = max(1, (int) $task->target);

            return [
                'id' => $task->id,
                'code' => $task->code,
                'title' => $task->title,
                'description' => $task->description,
                'source' => $task->source,
                'target' => $target,
                'progress' => min($current, $target),
                'xp_reward' => (int) $task->xp_reward,
                'icon' => $task->icon,
                'date' => $today,
                'is_completed' => $row?->isCompleted() ?? ($current >= $target),
                'is_claimed' => $row?->isClaimed() ?? false,
                'can_claim' => ($row?->isCompleted() ?? ($current >= $target)) && ! ($row?->isClaimed() ?? false),
            ];
        });
    }

    public function claimDailyTask(User $user, string $taskId): XpAwardResult
    {
        return DB::transaction(function () use ($user, $taskId): XpAwardResult {
            $task = DailyTask::query()
                ->whereKey($taskId)
                ->where('status', DailyTask::STATUS_ACTIVE)
                ->firstOrFail();

            $today = now()->toDateString();
            $row = UserDailyTaskProgress::query()
                ->where('user_id', $user->getKey())
                ->where('daily_task_id', $task->getKey())
                ->whereDate('task_date', $today)
                ->lockForUpdate()
                ->first();

            if ($row === null || ! $row->isCompleted()) {
                throw ValidationException::withMessages(['task' => 'This daily task is not completed yet.']);
            }

            if ($row->isClaimed()) {
                throw ValidationException::withMessages(['task' => 'This daily task reward was already claimed.']);
            }

            $result = $this->award(
                user: $user,
                source: XpTransaction::SOURCE_DAILY_TASK,
                amount: (int) $task->xp_reward,
                idempotencyKey: 'daily_task:'.$user->getKey().':'.$task->getKey().':'.$today,
                metadata: [
                    'daily_task_id' => $task->getKey(),
                    'code' => $task->code,
                    'date' => $today,
                ],
            );

            if ($result->awarded) {
                $row->update(['claimed_at' => now()]);
            }

            return $result;
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listBadges(User $user): Collection
    {
        $owned = UserBadge::query()
            ->where('user_id', $user->getKey())
            ->with('badge')
            ->get()
            ->keyBy(fn (UserBadge $award): string => (string) $award->badge_id);

        return Badge::query()
            ->where('status', Badge::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Badge $badge) use ($owned): array {
                $award = $owned->get((string) $badge->getKey());

                return [
                    'id' => $badge->id,
                    'code' => $badge->code,
                    'name' => $badge->name,
                    'description' => $badge->description,
                    'icon' => $badge->icon,
                    'animation_url' => $badge->animation_url,
                    'unlock_type' => $badge->unlock_type,
                    'unlock_value' => (int) $badge->unlock_value,
                    'xp_reward' => (int) $badge->xp_reward,
                    'is_unlocked' => $award !== null,
                    'awarded_at' => $award?->awarded_at?->toISOString(),
                ];
            });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function trackDailyTaskProgress(User $user, string $source, int $amount, array $metadata = []): void
    {
        if ($amount <= 0) {
            return;
        }

        $increment = match ($source) {
            XpTransaction::SOURCE_LOGIN => 1,
            XpTransaction::SOURCE_LIVE => max(1, (int) ($metadata['minutes'] ?? 1)),
            XpTransaction::SOURCE_WATCHING => max(1, (int) ($metadata['minutes'] ?? 1)),
            XpTransaction::SOURCE_GIFT_SEND => max(1, (int) ($metadata['coins'] ?? 1)),
            XpTransaction::SOURCE_GIFT_RECEIVE => max(1, (int) ($metadata['coins'] ?? 1)),
            default => 0,
        };

        if ($increment <= 0) {
            return;
        }

        $today = now()->toDateString();
        $tasks = DailyTask::query()
            ->where('status', DailyTask::STATUS_ACTIVE)
            ->where('source', $source)
            ->get();

        foreach ($tasks as $task) {
            $row = UserDailyTaskProgress::query()->firstOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'daily_task_id' => $task->getKey(),
                    'task_date' => $today,
                ],
                ['progress' => 0],
            );

            if ($row->isClaimed()) {
                continue;
            }

            $progress = min((int) $task->target, (int) $row->progress + $increment);
            $updates = ['progress' => $progress];
            if ($progress >= (int) $task->target && $row->completed_at === null) {
                $updates['completed_at'] = now();
            }
            $row->update($updates);
        }
    }

    public function evaluateBadges(User $user): void
    {
        $progress = $this->ensureProgress($user);
        $ownedIds = UserBadge::query()
            ->where('user_id', $user->getKey())
            ->pluck('badge_id')
            ->all();

        $candidates = Badge::query()
            ->where('status', Badge::STATUS_ACTIVE)
            ->when($ownedIds !== [], fn ($query) => $query->whereNotIn('id', $ownedIds))
            ->orderBy('sort_order')
            ->get();

        foreach ($candidates as $badge) {
            if (! $this->badgeUnlocked($user, $progress, $badge)) {
                continue;
            }

            $award = UserBadge::query()->firstOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'badge_id' => $badge->getKey(),
                ],
                [
                    'awarded_at' => now(),
                    'metadata' => [
                        'level' => $progress->level,
                        'xp_total' => $progress->xp_total,
                    ],
                ],
            );

            if (! $award->wasRecentlyCreated) {
                continue;
            }

            event(new BadgeUnlocked($award->load('badge')));

            if ((int) $badge->xp_reward > 0) {
                $this->award(
                    user: $user,
                    source: XpTransaction::SOURCE_BADGE,
                    amount: (int) $badge->xp_reward,
                    idempotencyKey: 'badge:'.$user->getKey().':'.$badge->getKey(),
                    metadata: [
                        'badge_id' => $badge->getKey(),
                        'code' => $badge->code,
                    ],
                );
            }
        }
    }

    private function badgeUnlocked(User $user, UserLevelProgress $progress, Badge $badge): bool
    {
        return match ($badge->unlock_type) {
            Badge::UNLOCK_LEVEL => (int) $progress->level >= (int) $badge->unlock_value,
            Badge::UNLOCK_XP_TOTAL => (int) $progress->xp_total >= (int) $badge->unlock_value,
            Badge::UNLOCK_GIFTS_SENT => (int) XpTransaction::query()
                ->where('user_id', $user->getKey())
                ->where('source', XpTransaction::SOURCE_GIFT_SEND)
                ->count() >= (int) $badge->unlock_value,
            Badge::UNLOCK_GIFTS_RECEIVED => (int) XpTransaction::query()
                ->where('user_id', $user->getKey())
                ->where('source', XpTransaction::SOURCE_GIFT_RECEIVE)
                ->count() >= (int) $badge->unlock_value,
            Badge::UNLOCK_LIVE_MINUTES => (int) XpTransaction::query()
                ->where('user_id', $user->getKey())
                ->where('source', XpTransaction::SOURCE_LIVE)
                ->get()
                ->sum(fn (XpTransaction $row): int => (int) ($row->metadata['minutes'] ?? 0)) >= (int) $badge->unlock_value,
            Badge::UNLOCK_LOGIN_STREAK => (int) XpTransaction::query()
                ->where('user_id', $user->getKey())
                ->where('source', XpTransaction::SOURCE_LOGIN)
                ->count() >= (int) $badge->unlock_value,
            default => false,
        };
    }
}

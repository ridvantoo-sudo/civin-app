<?php

namespace App\Features\Challenges\Services;

use App\Features\Challenges\DTOs\ChallengeClaimResult;
use App\Features\Challenges\Models\Challenge;
use App\Features\Challenges\Models\UserChallengeProgress;
use App\Features\Levels\Models\XpTransaction;
use App\Features\Levels\Services\LevelService;
use App\Features\Users\Models\User;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ChallengeService
{
    public function __construct(
        private LevelService $levels,
        private WalletRepository $wallets,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listChallenges(User $user, ?string $period = null): Collection
    {
        $query = Challenge::query()
            ->where('status', Challenge::STATUS_ACTIVE)
            ->orderBy('period')
            ->orderBy('sort_order')
            ->orderBy('title');

        if ($period !== null) {
            if (! in_array($period, Challenge::PERIODS, true)) {
                throw ValidationException::withMessages([
                    'period' => 'Period must be daily or weekly.',
                ]);
            }
            $query->where('period', $period);
        }

        $challenges = $query->get();
        $periodKeys = [
            Challenge::PERIOD_DAILY => $this->periodKey(Challenge::PERIOD_DAILY),
            Challenge::PERIOD_WEEKLY => $this->periodKey(Challenge::PERIOD_WEEKLY),
        ];

        $progress = UserChallengeProgress::query()
            ->where('user_id', $user->getKey())
            ->whereIn('challenge_id', $challenges->modelKeys())
            ->whereIn('period_key', array_values($periodKeys))
            ->get()
            ->keyBy(fn (UserChallengeProgress $row): string => $row->challenge_id.':'.$row->period_key);

        return $challenges->map(function (Challenge $challenge) use ($progress, $periodKeys): array {
            $periodKey = $periodKeys[$challenge->period] ?? $this->periodKey($challenge->period);
            $row = $progress->get($challenge->getKey().':'.$periodKey);

            return $this->serializeChallenge($challenge, $row, $periodKey);
        });
    }

    public function claim(User $user, string $challengeId): ChallengeClaimResult
    {
        return DB::transaction(function () use ($user, $challengeId): ChallengeClaimResult {
            $challenge = Challenge::query()
                ->whereKey($challengeId)
                ->where('status', Challenge::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($challenge === null) {
                throw ValidationException::withMessages([
                    'challenge' => 'Challenge not found.',
                ]);
            }

            $periodKey = $this->periodKey($challenge->period);
            $row = UserChallengeProgress::query()
                ->where('user_id', $user->getKey())
                ->where('challenge_id', $challenge->getKey())
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if ($row === null || ! $row->isCompleted()) {
                throw ValidationException::withMessages([
                    'challenge' => 'This challenge is not completed yet.',
                ]);
            }

            if ($row->isClaimed()) {
                throw ValidationException::withMessages([
                    'challenge' => 'This challenge reward was already claimed.',
                ]);
            }

            $xpReward = (int) $challenge->reward_xp;
            $coinsReward = (int) $challenge->reward_coins;
            $diamondsReward = (int) $challenge->reward_diamonds;

            $leveledUp = false;
            $levelsGained = 0;
            $levelProgress = null;

            if ($xpReward > 0) {
                $xpResult = $this->levels->award(
                    user: $user,
                    source: XpTransaction::SOURCE_CHALLENGE,
                    amount: $xpReward,
                    idempotencyKey: 'challenge:'.$user->getKey().':'.$challenge->getKey().':'.$periodKey,
                    metadata: [
                        'challenge_id' => $challenge->getKey(),
                        'code' => $challenge->code,
                        'period' => $challenge->period,
                        'period_key' => $periodKey,
                    ],
                );
                $levelProgress = $xpResult->progress;
                $leveledUp = $xpResult->leveledUp;
                $levelsGained = $xpResult->levelsGained;
                if (! $xpResult->awarded && $xpReward > 0) {
                    // Idempotent replay — still mark claimed if wallet rewards apply below.
                }
            } else {
                $levelProgress = $this->levels->progressFor($user);
            }

            $wallet = null;
            if ($coinsReward > 0 || $diamondsReward > 0) {
                $wallet = $this->wallets->applyChallengeReward(
                    user: $user,
                    progress: $row,
                    coins: $coinsReward,
                    diamonds: $diamondsReward,
                );
            } else {
                $wallet = $this->wallets->findOrCreateForUser($user);
            }

            $row->update([
                'claimed_at' => now(),
                'xp_awarded' => $xpReward,
                'coins_awarded' => $coinsReward,
                'diamonds_awarded' => $diamondsReward,
            ]);

            $fresh = $row->fresh('challenge') ?? $row;

            return new ChallengeClaimResult(
                claimed: true,
                challenge: $this->serializeChallenge($challenge, $fresh, $periodKey),
                progress: $fresh,
                xpAwarded: $xpReward,
                coinsAwarded: $coinsReward,
                diamondsAwarded: $diamondsReward,
                levelProgress: $levelProgress,
                wallet: $wallet,
                leveledUp: $leveledUp,
                levelsGained: $levelsGained,
            );
        });
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return UserChallengeProgress::query()
            ->where('user_id', $user->getKey())
            ->whereNotNull('claimed_at')
            ->with('challenge')
            ->orderByDesc('claimed_at')
            ->paginate($perPage);
    }

    public function track(User $user, string $taskType, int $amount, array $metadata = []): void
    {
        if ($amount <= 0 || ! in_array($taskType, Challenge::TASK_TYPES, true)) {
            return;
        }

        $challenges = Challenge::query()
            ->where('status', Challenge::STATUS_ACTIVE)
            ->where('task_type', $taskType)
            ->get();

        if ($challenges->isEmpty()) {
            return;
        }

        foreach ($challenges as $challenge) {
            $this->bump($user, $challenge, $amount);
        }
    }

    public function trackLogin(User $user): void
    {
        $this->track($user, Challenge::TASK_LOGIN, 1, ['date' => now()->toDateString()]);
    }

    public function trackWatching(User $user, int $minutes): void
    {
        $this->track($user, Challenge::TASK_WATCH, max(0, $minutes));
    }

    public function trackLive(User $user, int $minutes): void
    {
        $this->track($user, Challenge::TASK_LIVE, max(0, $minutes));
    }

    public function trackGiftSend(User $user, int $coins): void
    {
        $this->track($user, Challenge::TASK_GIFT_SEND, max(0, $coins));
    }

    public function trackGiftReceive(User $user, int $coins): void
    {
        $this->track($user, Challenge::TASK_GIFT_RECEIVE, max(0, $coins));
    }

    public function trackPk(User $user): void
    {
        $this->track($user, Challenge::TASK_PK, 1);
    }

    public function periodKey(string $period): string
    {
        return $period === Challenge::PERIOD_WEEKLY
            ? now()->format('o-\WW')
            : now()->toDateString();
    }

    private function bump(User $user, Challenge $challenge, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $challenge, $amount): void {
            $periodKey = $this->periodKey($challenge->period);
            $row = UserChallengeProgress::query()->firstOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'challenge_id' => $challenge->getKey(),
                    'period_key' => $periodKey,
                ],
                ['progress' => 0],
            );

            $locked = UserChallengeProgress::query()
                ->whereKey($row->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->isClaimed()) {
                return;
            }

            $target = max(1, (int) $challenge->target);
            $progress = min($target, (int) $locked->progress + $amount);
            $updates = ['progress' => $progress];

            if ($progress >= $target && $locked->completed_at === null) {
                $updates['completed_at'] = now();
            }

            $locked->update($updates);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeChallenge(
        Challenge $challenge,
        ?UserChallengeProgress $row,
        string $periodKey,
    ): array {
        $target = max(1, (int) $challenge->target);
        $current = (int) ($row?->progress ?? 0);

        return [
            'id' => $challenge->id,
            'code' => $challenge->code,
            'title' => $challenge->title,
            'description' => $challenge->description,
            'period' => $challenge->period,
            'period_key' => $periodKey,
            'task_type' => $challenge->task_type,
            'target' => $target,
            'progress' => min($current, $target),
            'reward_xp' => (int) $challenge->reward_xp,
            'reward_coins' => (int) $challenge->reward_coins,
            'reward_diamonds' => (int) $challenge->reward_diamonds,
            'icon' => $challenge->icon,
            'is_completed' => $row?->isCompleted() ?? ($current >= $target),
            'is_claimed' => $row?->isClaimed() ?? false,
            'can_claim' => ($row?->isCompleted() ?? ($current >= $target)) && ! ($row?->isClaimed() ?? false),
            'completed_at' => $row?->completed_at?->toISOString(),
            'claimed_at' => $row?->claimed_at?->toISOString(),
        ];
    }
}

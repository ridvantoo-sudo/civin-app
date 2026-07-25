<?php

namespace App\Features\SocialEngagement\Services;

use App\Features\SocialEngagement\Models\Achievement;
use App\Features\SocialEngagement\Models\UserAchievement;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AchievementService
{
    public function __construct(private WalletRepository $wallets) {}

    public function catalog(User $user): Collection
    {
        $this->ensureEligible($user);

        $progress = UserAchievement::query()
            ->where('user_id', $user->getKey())
            ->get()
            ->keyBy('achievement_id');

        return Achievement::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->get()
            ->each(function (Achievement $achievement) use ($progress): void {
                $row = $progress->get($achievement->getKey());
                $achievement->setAttribute('user_progress', $row?->progress ?? 0);
                $achievement->setAttribute('unlocked_at', $row?->unlocked_at);
                $achievement->setAttribute('claimed_at', $row?->claimed_at);
                $achievement->setAttribute('is_unlocked', $row?->unlocked_at !== null);
                $achievement->setAttribute('is_claimed', $row?->claimed_at !== null);
            });
    }

    public function increment(User $user, string $metric, int $amount = 1): void
    {
        if ($amount < 1 || $user->is_guest) {
            return;
        }

        $achievements = Achievement::query()
            ->where('active', true)
            ->where('metric', $metric)
            ->get();

        foreach ($achievements as $achievement) {
            $this->bump($user, $achievement, $amount);
        }
    }

    public function setProgress(User $user, string $metric, int $value): void
    {
        if ($user->is_guest) {
            return;
        }

        $achievements = Achievement::query()
            ->where('active', true)
            ->where('metric', $metric)
            ->get();

        foreach ($achievements as $achievement) {
            $row = UserAchievement::query()->firstOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'achievement_id' => $achievement->getKey(),
                ],
                ['progress' => 0],
            );

            if ($value > $row->progress) {
                $this->applyProgress($user, $achievement, $row, $value);
            }
        }
    }

    public function claim(User $user, Achievement $achievement): UserAchievement
    {
        $this->ensureEligible($user);

        return DB::transaction(function () use ($user, $achievement): UserAchievement {
            $row = UserAchievement::query()
                ->where('user_id', $user->getKey())
                ->where('achievement_id', $achievement->getKey())
                ->lockForUpdate()
                ->first();

            if ($row === null || $row->unlocked_at === null) {
                throw ValidationException::withMessages(['achievement' => 'Achievement is not unlocked yet.']);
            }
            if ($row->claimed_at !== null) {
                throw ValidationException::withMessages(['achievement' => 'Reward already claimed.']);
            }

            $row->forceFill(['claimed_at' => now()])->save();

            $coins = (int) $achievement->coins_reward;
            $diamonds = (int) $achievement->diamonds_reward;
            if ($coins > 0 || $diamonds > 0) {
                WalletUpdated::dispatch($this->wallets->applyGrowthReward(
                    $user,
                    $achievement,
                    $coins,
                    $diamonds,
                    ['kind' => 'achievement_claim', 'code' => $achievement->code],
                ));
            }

            return $row->load('achievement');
        });
    }

    private function bump(User $user, Achievement $achievement, int $amount): void
    {
        $row = UserAchievement::query()->firstOrCreate(
            [
                'user_id' => $user->getKey(),
                'achievement_id' => $achievement->getKey(),
            ],
            ['progress' => 0],
        );

        $this->applyProgress($user, $achievement, $row, $row->progress + $amount);
    }

    private function applyProgress(User $user, Achievement $achievement, UserAchievement $row, int $progress): void
    {
        $row->progress = $progress;
        $justUnlocked = false;

        if ($row->unlocked_at === null && $progress >= (int) $achievement->target) {
            $row->unlocked_at = now();
            $justUnlocked = true;
        }

        $row->save();

        if ($justUnlocked && config('social_engagement.achievements.auto_claim')) {
            try {
                $this->claim($user, $achievement);
            } catch (\Throwable) {
                // Already claimed or race — ignore.
            }
        }
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can view achievements.');
        }
    }
}

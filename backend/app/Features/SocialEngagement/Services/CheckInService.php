<?php

namespace App\Features\SocialEngagement\Services;

use App\Features\SocialEngagement\Events\UserCheckedIn;
use App\Features\SocialEngagement\Models\UserCheckIn;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CheckInService
{
    public function __construct(
        private WalletRepository $wallets,
        private AchievementService $achievements,
    ) {}

    /**
     * @return array{check_in: UserCheckIn, already_checked_in: bool, next_reward: array{coins: int, diamonds: int}|null}
     */
    public function status(User $user): array
    {
        $this->ensureEligible($user);
        $today = $this->today();
        $checkIn = UserCheckIn::query()
            ->where('user_id', $user->getKey())
            ->whereDate('check_in_date', $today)
            ->first();

        $last = UserCheckIn::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('check_in_date')
            ->first();

        $currentStreak = $this->currentStreak($last, $today);
        $nextDay = $checkIn !== null
            ? $currentStreak + 1
            : ($this->isContinuingStreak($last, $today) ? $currentStreak + 1 : 1);

        return [
            'today' => $today->toDateString(),
            'already_checked_in' => $checkIn !== null,
            'current_streak' => $currentStreak,
            'last_check_in' => $last,
            'today_check_in' => $checkIn,
            'next_reward' => $this->rewardForDay($nextDay),
            'calendar' => $this->rewardCalendar(),
        ];
    }

    public function checkIn(User $user): UserCheckIn
    {
        $this->ensureEligible($user);
        $today = $this->today();

        $existing = UserCheckIn::query()
            ->where('user_id', $user->getKey())
            ->whereDate('check_in_date', $today)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages(['check_in' => 'You already checked in today.']);
        }

        $last = UserCheckIn::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('check_in_date')
            ->first();

        $streakDay = 1;
        if ($last !== null && $this->isYesterday($last, $today)) {
            $streakDay = ((int) $last->streak_day) + 1;
        }

        $reward = $this->rewardForDay($streakDay);

        $checkIn = DB::transaction(function () use ($user, $today, $streakDay, $reward): UserCheckIn {
            $row = UserCheckIn::query()->create([
                'user_id' => $user->getKey(),
                'check_in_date' => $today->toDateString(),
                'streak_day' => $streakDay,
                'coins_awarded' => $reward['coins'],
                'diamonds_awarded' => $reward['diamonds'],
            ]);

            if ($reward['coins'] > 0 || $reward['diamonds'] > 0) {
                WalletUpdated::dispatch($this->wallets->applyGrowthReward(
                    $user,
                    $row,
                    $reward['coins'],
                    $reward['diamonds'],
                    ['kind' => 'daily_check_in', 'streak_day' => $streakDay],
                ));
            }

            return $row;
        });

        UserCheckedIn::dispatch($user, $checkIn);
        // Streak-based achievements (first_check_in, check_in_7, check_in_30).
        $this->achievements->setProgress($user, 'check_ins', $streakDay);

        return $checkIn;
    }

    /**
     * @return array{coins: int, diamonds: int}
     */
    private function rewardForDay(int $day): array
    {
        /** @var array<int, array{coins: int, diamonds: int}> $rewards */
        $rewards = config('social_engagement.check_in.rewards', []);
        if ($rewards === []) {
            return ['coins' => 10, 'diamonds' => 0];
        }

        $cycle = count($rewards);
        $index = (($day - 1) % $cycle) + 1;

        return [
            'coins' => (int) ($rewards[$index]['coins'] ?? 10),
            'diamonds' => (int) ($rewards[$index]['diamonds'] ?? 0),
        ];
    }

    /**
     * @return list<array{day: int, coins: int, diamonds: int}>
     */
    private function rewardCalendar(): array
    {
        /** @var array<int, array{coins: int, diamonds: int}> $rewards */
        $rewards = config('social_engagement.check_in.rewards', []);
        $out = [];
        foreach ($rewards as $day => $reward) {
            $out[] = [
                'day' => (int) $day,
                'coins' => (int) ($reward['coins'] ?? 0),
                'diamonds' => (int) ($reward['diamonds'] ?? 0),
            ];
        }

        return $out;
    }

    private function currentStreak(?UserCheckIn $last, Carbon $today): int
    {
        if ($last === null) {
            return 0;
        }
        $date = Carbon::parse($last->check_in_date)->startOfDay();
        if ($date->equalTo($today) || $this->isYesterday($last, $today)) {
            return (int) $last->streak_day;
        }

        return 0;
    }

    private function isContinuingStreak(?UserCheckIn $last, Carbon $today): bool
    {
        return $last !== null && $this->isYesterday($last, $today);
    }

    private function isYesterday(UserCheckIn $last, Carbon $today): bool
    {
        return Carbon::parse($last->check_in_date)->startOfDay()->equalTo($today->copy()->subDay());
    }

    private function today(): Carbon
    {
        $tz = (string) config('social_engagement.check_in.timezone', 'UTC');

        return now($tz)->startOfDay();
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can check in.');
        }
    }
}

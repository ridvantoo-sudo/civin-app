<?php

namespace App\Features\Couple\Repositories\Eloquent;

use App\Features\Blocking\Models\Block;
use App\Features\Couple\DTOs\SendCoupleGiftData;
use App\Features\Couple\DTOs\SendProposalData;
use App\Features\Couple\DTOs\UpdateCoupleProfileData;
use App\Features\Couple\Models\Couple;
use App\Features\Couple\Models\CoupleGift;
use App\Features\Couple\Models\CoupleProposal;
use App\Features\Couple\Models\CoupleTask;
use App\Features\Couple\Models\CoupleTaskProgress;
use App\Features\Couple\Repositories\Contracts\CoupleRepository;
use App\Features\Gifts\Models\Gift;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EloquentCoupleRepository implements CoupleRepository
{
    public function __construct(private readonly WalletRepository $wallets) {}

    public function find(string $coupleId): ?Couple
    {
        return Couple::query()
            ->with([
                'userOne.profile',
                'userOne.socialStatus',
                'userTwo.profile',
                'userTwo.socialStatus',
            ])
            ->find($coupleId);
    }

    public function findActiveForUser(User|string $user): ?Couple
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return Couple::query()
            ->where('status', Couple::STATUS_ACTIVE)
            ->where(function ($query) use ($userId): void {
                $query->where('user_one_id', $userId)
                    ->orWhere('user_two_id', $userId);
            })
            ->with([
                'userOne.profile',
                'userOne.socialStatus',
                'userTwo.profile',
                'userTwo.socialStatus',
            ])
            ->first();
    }

    public function propose(User $from, SendProposalData $data): CoupleProposal
    {
        return DB::transaction(function () use ($from, $data): CoupleProposal {
            $lockedFrom = User::query()->lockForUpdate()->findOrFail($from->getKey());
            $to = User::query()->lockForUpdate()->find($data->userId);

            if ($to === null || $to->status !== 'active' || $to->is_guest) {
                throw ValidationException::withMessages([
                    'user_id' => 'The selected user is invalid.',
                ]);
            }

            if ($lockedFrom->getKey() === $to->getKey()) {
                throw ValidationException::withMessages([
                    'user_id' => 'You cannot propose to yourself.',
                ]);
            }

            $this->ensureNotBlocked($lockedFrom, $to);

            if ($this->findActiveForUser($lockedFrom) !== null) {
                throw ValidationException::withMessages([
                    'user_id' => 'You are already in a relationship.',
                ]);
            }

            if ($this->findActiveForUser($to) !== null) {
                throw ValidationException::withMessages([
                    'user_id' => 'This user is already in a relationship.',
                ]);
            }

            $existing = CoupleProposal::query()
                ->where('status', CoupleProposal::STATUS_PENDING)
                ->where(function ($query) use ($lockedFrom, $to): void {
                    $query->where(function ($inner) use ($lockedFrom, $to): void {
                        $inner->where('from_user_id', $lockedFrom->getKey())
                            ->where('to_user_id', $to->getKey());
                    })->orWhere(function ($inner) use ($lockedFrom, $to): void {
                        $inner->where('from_user_id', $to->getKey())
                            ->where('to_user_id', $lockedFrom->getKey());
                    });
                })
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->isExpired()) {
                    $existing->forceFill([
                        'status' => CoupleProposal::STATUS_EXPIRED,
                        'responded_at' => now(),
                    ])->save();
                } else {
                    throw ValidationException::withMessages([
                        'user_id' => 'A pending proposal already exists between you.',
                    ]);
                }
            }

            $hours = max(1, min(720, $data->expiresInHours ?? 72));

            return CoupleProposal::query()->create([
                'from_user_id' => $lockedFrom->getKey(),
                'to_user_id' => $to->getKey(),
                'message' => $data->message,
                'status' => CoupleProposal::STATUS_PENDING,
                'expires_at' => now()->addHours($hours),
            ])->fresh([
                'fromUser.profile',
                'fromUser.socialStatus',
                'toUser.profile',
                'toUser.socialStatus',
            ]);
        });
    }

    public function accept(CoupleProposal $proposal, User $actor): Couple
    {
        return DB::transaction(function () use ($proposal, $actor): Couple {
            $locked = CoupleProposal::query()->lockForUpdate()->findOrFail($proposal->getKey());

            if ($locked->to_user_id !== $actor->getKey()) {
                throw ValidationException::withMessages([
                    'proposal' => 'Only the recipient can accept this proposal.',
                ]);
            }

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'proposal' => 'This proposal is no longer pending.',
                ]);
            }

            if ($locked->isExpired()) {
                $locked->forceFill([
                    'status' => CoupleProposal::STATUS_EXPIRED,
                    'responded_at' => now(),
                ])->save();

                throw ValidationException::withMessages([
                    'proposal' => 'This proposal has expired.',
                ]);
            }

            $from = User::query()->lockForUpdate()->findOrFail($locked->from_user_id);
            $to = User::query()->lockForUpdate()->findOrFail($locked->to_user_id);
            $this->ensureNotBlocked($from, $to);

            if ($this->findActiveForUser($from) !== null || $this->findActiveForUser($to) !== null) {
                throw ValidationException::withMessages([
                    'proposal' => 'One of you is already in a relationship.',
                ]);
            }

            [$userOne, $userTwo] = Couple::orderedPair($from->getKey(), $to->getKey());
            $now = now();

            $couple = Couple::query()
                ->where('user_one_id', $userOne)
                ->where('user_two_id', $userTwo)
                ->where('status', Couple::STATUS_ENDED)
                ->lockForUpdate()
                ->latest('ended_at')
                ->first();

            if ($couple !== null) {
                $couple->forceFill([
                    'proposer_id' => $from->getKey(),
                    'display_name' => null,
                    'cover_url' => null,
                    'badge_key' => Couple::BADGE_BRONZE,
                    'badge_url' => null,
                    'level' => 1,
                    'xp' => 0,
                    'xp_to_next_level' => Couple::xpRequiredForLevel(1),
                    'status' => Couple::STATUS_ACTIVE,
                    'anniversary_at' => $now,
                    'last_anniversary_bonus_year' => null,
                    'ranking_score' => 0,
                    'total_gifts_sent' => 0,
                    'ended_by' => null,
                    'ended_at' => null,
                ])->save();
            } else {
                $couple = Couple::query()->create([
                    'user_one_id' => $userOne,
                    'user_two_id' => $userTwo,
                    'proposer_id' => $from->getKey(),
                    'display_name' => null,
                    'cover_url' => null,
                    'badge_key' => Couple::BADGE_BRONZE,
                    'badge_url' => null,
                    'level' => 1,
                    'xp' => 0,
                    'xp_to_next_level' => Couple::xpRequiredForLevel(1),
                    'status' => Couple::STATUS_ACTIVE,
                    'anniversary_at' => $now,
                    'last_anniversary_bonus_year' => null,
                    'ranking_score' => 0,
                    'total_gifts_sent' => 0,
                ]);
            }

            $locked->forceFill([
                'status' => CoupleProposal::STATUS_ACCEPTED,
                'couple_id' => $couple->getKey(),
                'responded_at' => $now,
            ])->save();

            CoupleProposal::query()
                ->where('status', CoupleProposal::STATUS_PENDING)
                ->where(function ($query) use ($from, $to): void {
                    $query->whereIn('from_user_id', [$from->getKey(), $to->getKey()])
                        ->orWhereIn('to_user_id', [$from->getKey(), $to->getKey()]);
                })
                ->where('id', '!=', $locked->getKey())
                ->update([
                    'status' => CoupleProposal::STATUS_CANCELLED,
                    'responded_at' => $now,
                ]);

            return $couple->fresh([
                'userOne.profile',
                'userOne.socialStatus',
                'userTwo.profile',
                'userTwo.socialStatus',
            ]);
        });
    }

    public function reject(CoupleProposal $proposal, User $actor): CoupleProposal
    {
        return DB::transaction(function () use ($proposal, $actor): CoupleProposal {
            $locked = CoupleProposal::query()->lockForUpdate()->findOrFail($proposal->getKey());

            if ($locked->to_user_id !== $actor->getKey()) {
                throw ValidationException::withMessages([
                    'proposal' => 'Only the recipient can reject this proposal.',
                ]);
            }

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'proposal' => 'This proposal is no longer pending.',
                ]);
            }

            $locked->forceFill([
                'status' => CoupleProposal::STATUS_REJECTED,
                'responded_at' => now(),
            ])->save();

            return $locked->fresh([
                'fromUser.profile',
                'fromUser.socialStatus',
                'toUser.profile',
                'toUser.socialStatus',
            ]);
        });
    }

    public function cancel(CoupleProposal $proposal, User $actor): CoupleProposal
    {
        return DB::transaction(function () use ($proposal, $actor): CoupleProposal {
            $locked = CoupleProposal::query()->lockForUpdate()->findOrFail($proposal->getKey());

            if ($locked->from_user_id !== $actor->getKey()) {
                throw ValidationException::withMessages([
                    'proposal' => 'Only the sender can cancel this proposal.',
                ]);
            }

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'proposal' => 'This proposal is no longer pending.',
                ]);
            }

            $locked->forceFill([
                'status' => CoupleProposal::STATUS_CANCELLED,
                'responded_at' => now(),
            ])->save();

            return $locked->fresh([
                'fromUser.profile',
                'fromUser.socialStatus',
                'toUser.profile',
                'toUser.socialStatus',
            ]);
        });
    }

    public function listProposalsForUser(User $user): Collection
    {
        return CoupleProposal::query()
            ->where(function ($query) use ($user): void {
                $query->where('from_user_id', $user->getKey())
                    ->orWhere('to_user_id', $user->getKey());
            })
            ->whereIn('status', [
                CoupleProposal::STATUS_PENDING,
                CoupleProposal::STATUS_ACCEPTED,
                CoupleProposal::STATUS_REJECTED,
            ])
            ->with([
                'fromUser.profile',
                'fromUser.socialStatus',
                'toUser.profile',
                'toUser.socialStatus',
            ])
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(function (CoupleProposal $proposal): CoupleProposal {
                if ($proposal->isPending() && $proposal->isExpired()) {
                    $proposal->forceFill([
                        'status' => CoupleProposal::STATUS_EXPIRED,
                        'responded_at' => now(),
                    ])->save();
                }

                return $proposal;
            })
            ->filter(fn (CoupleProposal $proposal): bool => $proposal->status !== CoupleProposal::STATUS_EXPIRED)
            ->values();
    }

    public function updateProfile(Couple $couple, User $actor, UpdateCoupleProfileData $data): Couple
    {
        return DB::transaction(function () use ($couple, $actor, $data): Couple {
            $locked = Couple::query()->lockForUpdate()->findOrFail($couple->getKey());
            $this->ensurePartner($locked, $actor);

            $locked->forceFill([
                'display_name' => $data->displayName ?? $locked->display_name,
                'cover_url' => $data->coverUrl ?? $locked->cover_url,
            ])->save();

            return $locked->fresh([
                'userOne.profile',
                'userOne.socialStatus',
                'userTwo.profile',
                'userTwo.socialStatus',
            ]);
        });
    }

    public function end(Couple $couple, User $actor): Couple
    {
        return DB::transaction(function () use ($couple, $actor): Couple {
            $locked = Couple::query()->lockForUpdate()->findOrFail($couple->getKey());
            $this->ensurePartner($locked, $actor);

            $locked->forceFill([
                'status' => Couple::STATUS_ENDED,
                'ended_by' => $actor->getKey(),
                'ended_at' => now(),
            ])->save();

            return $locked->fresh([
                'userOne.profile',
                'userOne.socialStatus',
                'userTwo.profile',
                'userTwo.socialStatus',
            ]);
        });
    }

    public function rankings(int $perPage): LengthAwarePaginator
    {
        return Couple::query()
            ->where('status', Couple::STATUS_ACTIVE)
            ->with([
                'userOne.profile',
                'userOne.socialStatus',
                'userTwo.profile',
                'userTwo.socialStatus',
            ])
            ->orderByDesc('ranking_score')
            ->orderByDesc('level')
            ->orderByDesc('xp')
            ->paginate($perPage);
    }

    public function sendGift(Couple $couple, User $sender, SendCoupleGiftData $data): CoupleGift
    {
        return DB::transaction(function () use ($couple, $sender, $data): CoupleGift {
            $lockedCouple = Couple::query()->lockForUpdate()->findOrFail($couple->getKey());
            $this->ensurePartner($lockedCouple, $sender);
            $lockedSender = User::query()->lockForUpdate()->findOrFail($sender->getKey());

            $receiverId = $lockedCouple->partnerOf($lockedSender);
            if ($receiverId === null) {
                throw ValidationException::withMessages([
                    'couple' => 'You are not part of this couple.',
                ]);
            }

            $gift = Gift::query()->lockForUpdate()->find($data->giftId);
            if ($gift === null || $gift->status !== Gift::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'gift_id' => 'The selected gift is invalid.',
                ]);
            }

            $quantity = max(1, min(99, $data->quantity));
            $coins = (int) $gift->coin_price * $quantity;
            if ($coins < 1) {
                throw ValidationException::withMessages([
                    'gift_id' => 'Gift price is invalid.',
                ]);
            }

            $wallet = $this->wallets->lockForUser($lockedSender);
            if ($wallet->coins_balance < $coins) {
                throw ValidationException::withMessages([
                    'gift_id' => 'Insufficient coins.',
                ]);
            }

            $now = now();
            $xpAwarded = max(5, (int) floor($coins / 10));

            $wallet->decrement('coins_balance', $coins);
            WalletTransaction::query()->create([
                'user_id' => $lockedSender->getKey(),
                'type' => WalletTransaction::TYPE_COUPLE_GIFT,
                'amount' => -$coins,
                'currency' => WalletTransaction::CURRENCY_COINS,
                'reference_type' => Couple::class,
                'reference_id' => $lockedCouple->getKey(),
                'metadata' => [
                    'couple_id' => $lockedCouple->getKey(),
                    'gift_id' => $gift->getKey(),
                    'quantity' => $quantity,
                    'receiver_id' => $receiverId,
                ],
                'created_at' => $now,
            ]);

            $coupleGift = CoupleGift::query()->create([
                'couple_id' => $lockedCouple->getKey(),
                'sender_id' => $lockedSender->getKey(),
                'receiver_id' => $receiverId,
                'gift_id' => $gift->getKey(),
                'quantity' => $quantity,
                'coins' => $coins,
                'xp_awarded' => $xpAwarded,
                'metadata' => [
                    'gift_name' => $gift->name,
                    'gift_icon' => $gift->icon,
                ],
                'created_at' => $now,
            ]);

            $lockedCouple->increment('total_gifts_sent', $coins);
            $lockedCouple->increment('ranking_score', $coins);
            $this->bumpTask($lockedCouple, 'daily_gift', 1);
            $this->bumpTask($lockedCouple, 'weekly_gifts', $quantity);
            $this->awardXp($lockedCouple, $xpAwarded);

            return $coupleGift->fresh([
                'sender.profile',
                'sender.socialStatus',
                'receiver.profile',
                'receiver.socialStatus',
                'gift',
                'couple',
            ]);
        });
    }

    public function listGifts(Couple $couple, int $perPage): LengthAwarePaginator
    {
        return CoupleGift::query()
            ->where('couple_id', $couple->getKey())
            ->with([
                'sender.profile',
                'sender.socialStatus',
                'receiver.profile',
                'receiver.socialStatus',
                'gift',
            ])
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function listTasks(Couple $couple): Collection
    {
        $tasks = CoupleTask::query()
            ->where('is_active', true)
            ->orderBy('period')
            ->orderBy('title')
            ->get();

        return $tasks->map(function (CoupleTask $task) use ($couple): array {
            $periodKey = $this->periodKey($task->period);
            $progress = CoupleTaskProgress::query()
                ->where('couple_id', $couple->getKey())
                ->where('task_id', $task->getKey())
                ->where('period_key', $periodKey)
                ->first();

            return [
                'id' => $task->id,
                'key' => $task->key,
                'title' => $task->title,
                'description' => $task->description,
                'period' => $task->period,
                'target_value' => $task->target_value,
                'reward_xp' => $task->reward_xp,
                'progress' => $progress?->progress ?? 0,
                'completed_at' => $progress?->completed_at?->toISOString(),
                'claimed_at' => $progress?->claimed_at?->toISOString(),
                'is_claimable' => $progress !== null
                    && $progress->isCompleted()
                    && ! $progress->isClaimed(),
            ];
        });
    }

    public function claimTask(Couple $couple, User $actor, string $taskId): CoupleTaskProgress
    {
        return DB::transaction(function () use ($couple, $actor, $taskId): CoupleTaskProgress {
            $lockedCouple = Couple::query()->lockForUpdate()->findOrFail($couple->getKey());
            $this->ensurePartner($lockedCouple, $actor);

            $task = CoupleTask::query()->lockForUpdate()->find($taskId);
            if ($task === null || ! $task->is_active) {
                throw ValidationException::withMessages([
                    'task' => 'Task not found.',
                ]);
            }

            $periodKey = $this->periodKey($task->period);
            $progress = CoupleTaskProgress::query()
                ->where('couple_id', $lockedCouple->getKey())
                ->where('task_id', $task->getKey())
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if ($progress === null || ! $progress->isCompleted()) {
                throw ValidationException::withMessages([
                    'task' => 'Task is not completed yet.',
                ]);
            }

            if ($progress->isClaimed()) {
                throw ValidationException::withMessages([
                    'task' => 'Task reward already claimed.',
                ]);
            }

            $progress->forceFill(['claimed_at' => now()])->save();
            $this->awardXp($lockedCouple, $task->reward_xp);

            return $progress->fresh('task');
        });
    }

    public function checkIn(Couple $couple, User $actor): Couple
    {
        return DB::transaction(function () use ($couple, $actor): Couple {
            $lockedCouple = Couple::query()->lockForUpdate()->findOrFail($couple->getKey());
            $this->ensurePartner($lockedCouple, $actor);

            $dailyTask = CoupleTask::query()->where('key', 'daily_checkin')->where('is_active', true)->first();
            if ($dailyTask !== null) {
                $periodKey = $this->periodKey($dailyTask->period);
                $existing = CoupleTaskProgress::query()
                    ->where('couple_id', $lockedCouple->getKey())
                    ->where('task_id', $dailyTask->getKey())
                    ->where('period_key', $periodKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null && $existing->progress >= 1) {
                    throw ValidationException::withMessages([
                        'checkin' => 'You already checked in today.',
                    ]);
                }
            }

            $this->bumpTask($lockedCouple, 'daily_checkin', 1);
            $this->bumpTask($lockedCouple, 'weekly_checkins', 1);
            $this->awardXp($lockedCouple, 5);

            return $lockedCouple->fresh([
                'userOne.profile',
                'userOne.socialStatus',
                'userTwo.profile',
                'userTwo.socialStatus',
            ]);
        });
    }

    public function anniversaryInfo(Couple $couple): array
    {
        $anniversaryAt = $couple->anniversary_at?->copy();
        $now = now();

        if ($anniversaryAt === null) {
            return [
                'couple_id' => $couple->id,
                'anniversary_at' => null,
                'days_together' => 0,
                'years_together' => 0,
                'next_anniversary_at' => null,
                'days_until_next' => null,
                'is_anniversary_today' => false,
                'anniversary_bonus_claimable' => false,
            ];
        }

        $daysTogether = $anniversaryAt->copy()->startOfDay()->diffInDays($now->copy()->startOfDay());
        $yearsTogether = $anniversaryAt->diffInYears($now);

        $next = $anniversaryAt->copy()->year((int) $now->year);
        if ($next->copy()->startOfDay()->lt($now->copy()->startOfDay())) {
            $next->addYear();
        }

        $isToday = $anniversaryAt->isBirthday($now);
        $bonusYear = (int) $now->year;
        $bonusClaimable = $isToday
            && $yearsTogether >= 1
            && (int) ($couple->last_anniversary_bonus_year ?? 0) < $bonusYear;

        return [
            'couple_id' => $couple->id,
            'anniversary_at' => $anniversaryAt->toISOString(),
            'days_together' => (int) $daysTogether,
            'years_together' => (int) $yearsTogether,
            'next_anniversary_at' => $next->toISOString(),
            'days_until_next' => (int) $now->copy()->startOfDay()->diffInDays($next->copy()->startOfDay()),
            'is_anniversary_today' => $isToday,
            'anniversary_bonus_claimable' => $bonusClaimable,
        ];
    }

    public function claimAnniversaryBonus(Couple $couple, User $actor): Couple
    {
        return DB::transaction(function () use ($couple, $actor): Couple {
            $lockedCouple = Couple::query()->lockForUpdate()->findOrFail($couple->getKey());
            $this->ensurePartner($lockedCouple, $actor);

            $info = $this->anniversaryInfo($lockedCouple);
            if (! $info['anniversary_bonus_claimable']) {
                throw ValidationException::withMessages([
                    'anniversary' => 'Anniversary bonus is not available.',
                ]);
            }

            $year = (int) now()->year;
            $lockedCouple->forceFill([
                'last_anniversary_bonus_year' => $year,
            ])->save();

            $bonusXp = 100 + ((int) $info['years_together'] * 50);
            $this->awardXp($lockedCouple, $bonusXp);

            return $lockedCouple->fresh([
                'userOne.profile',
                'userOne.socialStatus',
                'userTwo.profile',
                'userTwo.socialStatus',
            ]);
        });
    }

    private function ensurePartner(Couple $couple, User $user): void
    {
        if (! $couple->isActive() || ! $couple->includesUser($user)) {
            throw ValidationException::withMessages([
                'couple' => 'You must be an active partner in this relationship.',
            ]);
        }
    }

    private function ensureNotBlocked(User $a, User $b): void
    {
        $blocked = Block::query()
            ->where(function ($query) use ($a, $b): void {
                $query->where('blocker_id', $a->getKey())->where('blocked_id', $b->getKey());
            })
            ->orWhere(function ($query) use ($a, $b): void {
                $query->where('blocker_id', $b->getKey())->where('blocked_id', $a->getKey());
            })
            ->exists();

        if ($blocked) {
            throw ValidationException::withMessages([
                'user_id' => 'You cannot interact with this user.',
            ]);
        }
    }

    private function bumpTask(Couple $couple, string $key, int $amount): void
    {
        $task = CoupleTask::query()->where('key', $key)->where('is_active', true)->first();
        if ($task === null) {
            return;
        }

        $periodKey = $this->periodKey($task->period);
        $progress = CoupleTaskProgress::query()->firstOrCreate(
            [
                'couple_id' => $couple->getKey(),
                'task_id' => $task->getKey(),
                'period_key' => $periodKey,
            ],
            [
                'progress' => 0,
            ],
        );

        if ($progress->isClaimed()) {
            return;
        }

        // Daily check-in should only count once per day.
        if ($key === 'daily_checkin' && $progress->progress >= 1) {
            return;
        }

        $progress->progress = min($task->target_value, $progress->progress + $amount);
        if ($progress->progress >= $task->target_value && $progress->completed_at === null) {
            $progress->completed_at = now();
        }
        $progress->save();
    }

    private function awardXp(Couple $couple, int $xp): void
    {
        if ($xp < 1) {
            return;
        }

        $couple->refresh();

        $level = (int) $couple->level;
        $currentXp = (int) $couple->xp + $xp;
        $toNext = (int) $couple->xp_to_next_level;

        while ($currentXp >= $toNext) {
            $currentXp -= $toNext;
            $level++;
            $toNext = Couple::xpRequiredForLevel($level);
        }

        $couple->forceFill([
            'xp' => $currentXp,
            'level' => $level,
            'xp_to_next_level' => $toNext,
            'badge_key' => Couple::badgeKeyForLevel($level),
            'ranking_score' => (int) $couple->ranking_score + $xp,
        ])->save();
    }

    private function periodKey(string $period): string
    {
        return $period === CoupleTask::PERIOD_WEEKLY
            ? now()->format('o-\WW')
            : now()->toDateString();
    }
}

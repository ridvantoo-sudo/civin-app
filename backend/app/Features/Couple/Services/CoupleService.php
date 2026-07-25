<?php

namespace App\Features\Couple\Services;

use App\Features\Couple\DTOs\SendCoupleGiftData;
use App\Features\Couple\DTOs\SendProposalData;
use App\Features\Couple\DTOs\UpdateCoupleProfileData;
use App\Features\Couple\Models\Couple;
use App\Features\Couple\Models\CoupleGift;
use App\Features\Couple\Models\CoupleProposal;
use App\Features\Couple\Models\CoupleTaskProgress;
use App\Features\Couple\Repositories\Contracts\CoupleRepository;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class CoupleService
{
    private const ACTION_RATE_KEY = 'couple-action:%s';

    private const RATE_MAX = 20;

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(private CoupleRepository $couples) {}

    public function show(Couple $couple): Couple
    {
        return $this->couples->find((string) $couple->getKey())
            ?? $couple->loadMissing([
                'userOne.profile',
                'userOne.socialStatus',
                'userTwo.profile',
                'userTwo.socialStatus',
            ]);
    }

    public function mine(User $user): ?Couple
    {
        return $this->couples->findActiveForUser($user);
    }

    public function propose(User $from, SendProposalData $data): CoupleProposal
    {
        $this->ensureEligible($from);
        $this->enforceRateLimit(sprintf(self::ACTION_RATE_KEY, $from->getKey()));

        return $this->couples->propose($from, $data);
    }

    public function accept(CoupleProposal $proposal, User $actor): Couple
    {
        $this->ensureEligible($actor);
        $this->enforceRateLimit(sprintf(self::ACTION_RATE_KEY, $actor->getKey()));

        return $this->couples->accept($proposal, $actor);
    }

    public function reject(CoupleProposal $proposal, User $actor): CoupleProposal
    {
        $this->ensureEligible($actor);

        return $this->couples->reject($proposal, $actor);
    }

    public function cancel(CoupleProposal $proposal, User $actor): CoupleProposal
    {
        $this->ensureEligible($actor);

        return $this->couples->cancel($proposal, $actor);
    }

    public function proposals(User $user): Collection
    {
        return $this->couples->listProposalsForUser($user);
    }

    public function updateProfile(Couple $couple, User $actor, UpdateCoupleProfileData $data): Couple
    {
        $this->ensureEligible($actor);

        return $this->couples->updateProfile($couple, $actor, $data);
    }

    public function end(Couple $couple, User $actor): Couple
    {
        $this->ensureEligible($actor);

        return $this->couples->end($couple, $actor);
    }

    public function rankings(int $perPage = 20): LengthAwarePaginator
    {
        return $this->couples->rankings(max(1, min($perPage, 100)));
    }

    public function sendGift(Couple $couple, User $sender, SendCoupleGiftData $data): CoupleGift
    {
        $this->ensureEligible($sender);
        $this->enforceRateLimit(sprintf(self::ACTION_RATE_KEY, $sender->getKey()));

        return $this->couples->sendGift($couple, $sender, $data);
    }

    public function gifts(Couple $couple, int $perPage = 20): LengthAwarePaginator
    {
        return $this->couples->listGifts($couple, max(1, min($perPage, 100)));
    }

    public function tasks(Couple $couple): Collection
    {
        return $this->couples->listTasks($couple);
    }

    public function claimTask(Couple $couple, User $actor, string $taskId): CoupleTaskProgress
    {
        $this->ensureEligible($actor);

        return $this->couples->claimTask($couple, $actor, $taskId);
    }

    public function checkIn(Couple $couple, User $actor): Couple
    {
        $this->ensureEligible($actor);
        $this->enforceRateLimit(sprintf(self::ACTION_RATE_KEY, $actor->getKey()));

        return $this->couples->checkIn($couple, $actor);
    }

    public function anniversary(Couple $couple): array
    {
        return $this->couples->anniversaryInfo($couple);
    }

    public function claimAnniversary(Couple $couple, User $actor): Couple
    {
        $this->ensureEligible($actor);

        return $this->couples->claimAnniversaryBonus($couple, $actor);
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can use Couple System.');
        }
    }

    private function enforceRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, self::RATE_MAX)) {
            throw ValidationException::withMessages([
                'couple' => 'Too many couple actions. Please wait a moment.',
            ]);
        }

        RateLimiter::hit($key, self::RATE_DECAY_SECONDS);
    }
}

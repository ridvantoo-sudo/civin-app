<?php

namespace App\Features\SocialEngagement\Services;

use App\Features\SocialEngagement\DTOs\LogMentorshipSessionData;
use App\Features\SocialEngagement\DTOs\RequestMentorshipData;
use App\Features\SocialEngagement\Events\MentorshipStarted;
use App\Features\SocialEngagement\Models\Mentorship;
use App\Features\SocialEngagement\Models\MentorshipRequest;
use App\Features\SocialEngagement\Models\MentorshipSession;
use App\Features\SocialEngagement\Support\MentorshipRequestStatus;
use App\Features\SocialEngagement\Support\MentorshipStatus;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class MentorshipService
{
    public function __construct(
        private WalletRepository $wallets,
        private AchievementService $achievements,
    ) {}

    public function request(User $requester, RequestMentorshipData $data): MentorshipRequest
    {
        $this->ensureEligible($requester);
        $this->enforceRateLimit('social-mentor-request:'.$requester->getKey());

        if (! in_array($data->role, ['mentor', 'mentee'], true)) {
            throw ValidationException::withMessages(['role' => 'Role must be mentor or mentee.']);
        }

        if ($data->targetUserId === $requester->getKey()) {
            throw ValidationException::withMessages(['target_user_id' => 'You cannot request mentorship with yourself.']);
        }

        $target = User::query()->find($data->targetUserId);
        if ($target === null || $target->is_guest || $target->status !== 'active') {
            throw ValidationException::withMessages(['target_user_id' => 'Target user is invalid.']);
        }

        if (MentorshipRequest::query()
            ->where('requester_id', $requester->getKey())
            ->where('target_id', $target->getKey())
            ->where('status', MentorshipRequestStatus::PENDING)
            ->exists()) {
            throw ValidationException::withMessages(['request' => 'You already have a pending request with this user.']);
        }

        return MentorshipRequest::query()->create([
            'requester_id' => $requester->getKey(),
            'target_id' => $target->getKey(),
            'role' => $data->role,
            'message' => $data->message,
            'status' => MentorshipRequestStatus::PENDING,
        ]);
    }

    public function accept(User $actor, MentorshipRequest $request): Mentorship
    {
        $this->ensureEligible($actor);

        if ($request->target_id !== $actor->getKey()) {
            throw new AuthorizationException('Only the target can accept this request.');
        }

        if ($request->status !== MentorshipRequestStatus::PENDING) {
            throw ValidationException::withMessages(['request' => 'Request is no longer pending.']);
        }

        // Resolve mentor/mentee from the role the requester asked for.
        if ($request->role === 'mentor') {
            $mentorId = $request->requester_id;
            $menteeId = $request->target_id;
        } else {
            $mentorId = $request->target_id;
            $menteeId = $request->requester_id;
        }

        $this->assertCanFormPair($mentorId, $menteeId);

        $mentorship = DB::transaction(function () use ($actor, $request, $mentorId, $menteeId): Mentorship {
            $locked = MentorshipRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if ($locked->status !== MentorshipRequestStatus::PENDING) {
                throw ValidationException::withMessages(['request' => 'Request is no longer pending.']);
            }

            $locked->forceFill([
                'status' => MentorshipRequestStatus::ACCEPTED,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();

            $existing = Mentorship::query()
                ->where('mentor_id', $mentorId)
                ->where('mentee_id', $menteeId)
                ->first();

            if ($existing !== null && $existing->status === MentorshipStatus::ACTIVE) {
                return $existing;
            }

            $mentorship = Mentorship::query()->updateOrCreate(
                ['mentor_id' => $mentorId, 'mentee_id' => $menteeId],
                [
                    'request_id' => $locked->getKey(),
                    'status' => MentorshipStatus::ACTIVE,
                    'started_at' => now(),
                    'ended_at' => null,
                ],
            );

            $mentorCoins = (int) config('social_engagement.mentorship.mentor_reward_coins', 50);
            $menteeCoins = (int) config('social_engagement.mentorship.mentee_reward_coins', 25);

            $mentor = User::query()->findOrFail($mentorId);
            $mentee = User::query()->findOrFail($menteeId);

            WalletUpdated::dispatch($this->wallets->applyGrowthReward($mentor, $mentorship, $mentorCoins, 0, ['kind' => 'mentorship_start_mentor']));
            WalletUpdated::dispatch($this->wallets->applyGrowthReward($mentee, $mentorship, $menteeCoins, 0, ['kind' => 'mentorship_start_mentee']));

            return $mentorship;
        });

        $mentorship->load(['mentor.profile', 'mentee.profile']);
        MentorshipStarted::dispatch($mentorship);
        $this->achievements->increment($mentorship->mentor, 'mentorships', 1);
        $this->achievements->increment($mentorship->mentee, 'mentorships', 1);

        return $mentorship;
    }

    public function reject(User $actor, MentorshipRequest $request): MentorshipRequest
    {
        $this->ensureEligible($actor);
        if ($request->target_id !== $actor->getKey()) {
            throw new AuthorizationException('Only the target can reject this request.');
        }
        if ($request->status !== MentorshipRequestStatus::PENDING) {
            throw ValidationException::withMessages(['request' => 'Request is no longer pending.']);
        }

        $request->forceFill([
            'status' => MentorshipRequestStatus::REJECTED,
            'reviewed_by' => $actor->getKey(),
            'reviewed_at' => now(),
        ])->save();

        return $request;
    }

    public function end(User $actor, Mentorship $mentorship): Mentorship
    {
        $this->ensureEligible($actor);
        if ($mentorship->mentor_id !== $actor->getKey() && $mentorship->mentee_id !== $actor->getKey()) {
            throw new AuthorizationException('You are not part of this mentorship.');
        }
        if ($mentorship->status !== MentorshipStatus::ACTIVE) {
            throw ValidationException::withMessages(['mentorship' => 'Mentorship is not active.']);
        }

        $mentorship->forceFill([
            'status' => MentorshipStatus::ENDED,
            'ended_at' => now(),
        ])->save();

        return $mentorship;
    }

    public function logSession(User $actor, Mentorship $mentorship, LogMentorshipSessionData $data): MentorshipSession
    {
        $this->ensureEligible($actor);
        if ($mentorship->mentor_id !== $actor->getKey() && $mentorship->mentee_id !== $actor->getKey()) {
            throw new AuthorizationException('You are not part of this mentorship.');
        }
        if ($mentorship->status !== MentorshipStatus::ACTIVE) {
            throw ValidationException::withMessages(['mentorship' => 'Mentorship is not active.']);
        }
        if ($data->durationMinutes < 1) {
            throw ValidationException::withMessages(['duration_minutes' => 'Duration must be at least 1 minute.']);
        }

        $mentorCoins = (int) config('social_engagement.mentorship.session_mentor_coins', 20);
        $menteeCoins = (int) config('social_engagement.mentorship.session_mentee_coins', 10);

        return DB::transaction(function () use ($actor, $mentorship, $data, $mentorCoins, $menteeCoins): MentorshipSession {
            $session = MentorshipSession::query()->create([
                'mentorship_id' => $mentorship->getKey(),
                'logged_by' => $actor->getKey(),
                'duration_minutes' => $data->durationMinutes,
                'notes' => $data->notes,
                'mentor_coins' => $mentorCoins,
                'mentee_coins' => $menteeCoins,
                'held_at' => $data->heldAt ?? now(),
            ]);

            $mentor = User::query()->findOrFail($mentorship->mentor_id);
            $mentee = User::query()->findOrFail($mentorship->mentee_id);

            WalletUpdated::dispatch($this->wallets->applyGrowthReward($mentor, $session, $mentorCoins, 0, ['kind' => 'mentorship_session_mentor']));
            WalletUpdated::dispatch($this->wallets->applyGrowthReward($mentee, $session, $menteeCoins, 0, ['kind' => 'mentorship_session_mentee']));

            return $session;
        });
    }

    public function mine(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return Mentorship::query()
            ->where(function ($q) use ($user): void {
                $q->where('mentor_id', $user->getKey())->orWhere('mentee_id', $user->getKey());
            })
            ->with(['mentor.profile', 'mentee.profile'])
            ->latest()
            ->paginate(max(1, min($perPage, 100)));
    }

    public function incomingRequests(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return MentorshipRequest::query()
            ->where('target_id', $user->getKey())
            ->where('status', MentorshipRequestStatus::PENDING)
            ->with(['requester.profile'])
            ->latest()
            ->paginate(max(1, min($perPage, 100)));
    }

    private function assertCanFormPair(string $mentorId, string $menteeId): void
    {
        $maxMentees = (int) config('social_engagement.mentorship.max_mentees_per_mentor', 10);
        $activeMentees = Mentorship::query()
            ->where('mentor_id', $mentorId)
            ->where('status', MentorshipStatus::ACTIVE)
            ->count();
        if ($activeMentees >= $maxMentees) {
            throw ValidationException::withMessages(['mentorship' => 'Mentor has reached the mentee limit.']);
        }

        $maxAsMentee = (int) config('social_engagement.mentorship.max_active_as_mentee', 1);
        $asMentee = Mentorship::query()
            ->where('mentee_id', $menteeId)
            ->where('status', MentorshipStatus::ACTIVE)
            ->count();
        if ($asMentee >= $maxAsMentee) {
            throw ValidationException::withMessages(['mentorship' => 'Mentee already has an active mentor.']);
        }
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can use mentorship.');
        }
    }

    private function enforceRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, 20)) {
            throw ValidationException::withMessages(['request' => 'Too many mentorship requests.']);
        }
        RateLimiter::hit($key, 60);
    }
}

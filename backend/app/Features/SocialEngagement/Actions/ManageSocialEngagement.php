<?php

namespace App\Features\SocialEngagement\Actions;

use App\Features\SocialEngagement\DTOs\LogMentorshipSessionData;
use App\Features\SocialEngagement\DTOs\RequestMentorshipData;
use App\Features\SocialEngagement\Models\Achievement;
use App\Features\SocialEngagement\Models\Mentorship;
use App\Features\SocialEngagement\Models\MentorshipRequest;
use App\Features\SocialEngagement\Models\MentorshipSession;
use App\Features\SocialEngagement\Models\UserAchievement;
use App\Features\SocialEngagement\Models\UserCheckIn;
use App\Features\SocialEngagement\Services\AchievementService;
use App\Features\SocialEngagement\Services\CheckInService;
use App\Features\SocialEngagement\Services\MentorshipService;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final readonly class ManageSocialEngagement
{
    public function __construct(
        private MentorshipService $mentorship,
        private AchievementService $achievements,
        private CheckInService $checkIns,
    ) {}

    public function requestMentorship(User $user, RequestMentorshipData $data): MentorshipRequest
    {
        return $this->mentorship->request($user, $data);
    }

    public function acceptMentorship(User $user, MentorshipRequest $request): Mentorship
    {
        return $this->mentorship->accept($user, $request);
    }

    public function rejectMentorship(User $user, MentorshipRequest $request): MentorshipRequest
    {
        return $this->mentorship->reject($user, $request);
    }

    public function endMentorship(User $user, Mentorship $mentorship): Mentorship
    {
        return $this->mentorship->end($user, $mentorship);
    }

    public function logSession(User $user, Mentorship $mentorship, LogMentorshipSessionData $data): MentorshipSession
    {
        return $this->mentorship->logSession($user, $mentorship, $data);
    }

    public function myMentorships(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->mentorship->mine($user, $perPage);
    }

    public function incomingMentorshipRequests(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->mentorship->incomingRequests($user, $perPage);
    }

    public function achievementCatalog(User $user): Collection
    {
        return $this->achievements->catalog($user);
    }

    public function claimAchievement(User $user, Achievement $achievement): UserAchievement
    {
        return $this->achievements->claim($user, $achievement);
    }

    public function checkInStatus(User $user): array
    {
        return $this->checkIns->status($user);
    }

    public function checkIn(User $user): UserCheckIn
    {
        return $this->checkIns->checkIn($user);
    }
}

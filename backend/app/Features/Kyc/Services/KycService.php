<?php

namespace App\Features\Kyc\Services;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Kyc\DTOs\ReviewKycData;
use App\Features\Kyc\DTOs\SubmitKycData;
use App\Features\Kyc\Models\KycSubmission;
use App\Features\Kyc\Notifications\KycReviewedNotification;
use App\Features\Kyc\Repositories\Contracts\KycRepository;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class KycService
{
    private const SUBMIT_RATE_KEY = 'kyc-submit:%s';

    private const RATE_MAX = 5;

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private KycRepository $kyc,
        private FaceMatchService $faceMatch,
    ) {}

    public function me(User $user): ?KycSubmission
    {
        $this->ensureEligible($user);

        return $this->kyc->findByUser($user);
    }

    public function isWithdrawalVerified(User $user): bool
    {
        $submission = $this->kyc->findByUser($user);

        return $submission !== null && $submission->allowsWithdrawal();
    }

    public function submit(User $user, SubmitKycData $data): KycSubmission
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::SUBMIT_RATE_KEY, $user->getKey()), 'submission');

        if ($data->documentType === KycSubmission::DOCUMENT_NATIONAL_ID
            && ($data->documentBackPath === null || $data->documentBackPath === '')) {
            throw ValidationException::withMessages([
                'document_back' => 'National ID requires a back-side photo.',
            ]);
        }

        $match = $this->faceMatch->compare($data->documentFrontPath, $data->selfiePath);

        return $this->kyc->submit(
            $user,
            $data,
            $match['score'],
            $match['status'],
        );
    }

    public function review(KycSubmission $submission, User $reviewer, ReviewKycData $data): KycSubmission
    {
        $this->ensureAdmin($reviewer);

        $reviewed = $this->kyc->review($submission, $reviewer, $data);

        if (in_array($reviewed->status, [
            KycSubmission::STATUS_APPROVED,
            KycSubmission::STATUS_REJECTED,
        ], true)) {
            $reviewed->user?->notify(new KycReviewedNotification($reviewed));
        }

        return $reviewed;
    }

    public function listForAdmin(User $admin, ?string $status, int $perPage): LengthAwarePaginator
    {
        $this->ensureAdmin($admin);

        return $this->kyc->listForAdmin($status, max(1, min($perPage, 100)));
    }

    private function ensureEligible(User $user): void
    {
        if ($user->status !== 'active' || $user->is_guest) {
            throw new AuthorizationException('Only active registered users can use KYC.');
        }
    }

    private function ensureAdmin(User $user): void
    {
        if ($user->is_admin || $user->can(AdminPermission::REVIEW_KYC)) {
            return;
        }

        throw new AuthorizationException('You are not allowed to review KYC submissions.');
    }

    private function enforceRateLimit(string $key, string $action): void
    {
        if (RateLimiter::tooManyAttempts($key, self::RATE_MAX)) {
            throw ValidationException::withMessages([
                'kyc' => "Too many KYC {$action} attempts. Please wait a moment.",
            ]);
        }

        RateLimiter::hit($key, self::RATE_DECAY_SECONDS);
    }
}

<?php

namespace App\Features\Kyc\Repositories\Contracts;

use App\Features\Kyc\DTOs\ReviewKycData;
use App\Features\Kyc\DTOs\SubmitKycData;
use App\Features\Kyc\Models\KycSubmission;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface KycRepository
{
    public function findByUser(User $user): ?KycSubmission;

    public function find(string $id): ?KycSubmission;

    public function submit(User $user, SubmitKycData $data, ?float $faceMatchScore, string $faceMatchStatus): KycSubmission;

    public function review(KycSubmission $submission, User $reviewer, ReviewKycData $data): KycSubmission;

    public function listForAdmin(?string $status, int $perPage): LengthAwarePaginator;
}

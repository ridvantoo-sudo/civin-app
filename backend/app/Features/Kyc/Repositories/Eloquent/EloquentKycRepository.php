<?php

namespace App\Features\Kyc\Repositories\Eloquent;

use App\Features\Kyc\DTOs\ReviewKycData;
use App\Features\Kyc\DTOs\SubmitKycData;
use App\Features\Kyc\Models\KycAuditLog;
use App\Features\Kyc\Models\KycSubmission;
use App\Features\Kyc\Repositories\Contracts\KycRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class EloquentKycRepository implements KycRepository
{
    public function findByUser(User $user): ?KycSubmission
    {
        return KycSubmission::query()
            ->where('user_id', $user->getKey())
            ->with(['user.profile', 'user.socialStatus', 'reviewer'])
            ->first();
    }

    public function find(string $id): ?KycSubmission
    {
        return KycSubmission::query()
            ->with(['user.profile', 'user.socialStatus', 'reviewer'])
            ->find($id);
    }

    public function submit(
        User $user,
        SubmitKycData $data,
        ?float $faceMatchScore,
        string $faceMatchStatus,
    ): KycSubmission {
        return DB::transaction(function () use ($user, $data, $faceMatchScore, $faceMatchStatus): KycSubmission {
            $existing = KycSubmission::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->status === KycSubmission::STATUS_APPROVED) {
                    throw ValidationException::withMessages([
                        'kyc' => 'Your identity is already verified.',
                    ]);
                }

                if (in_array($existing->status, [
                    KycSubmission::STATUS_PENDING,
                    KycSubmission::STATUS_UNDER_REVIEW,
                ], true)) {
                    throw ValidationException::withMessages([
                        'kyc' => 'Your KYC submission is already pending admin review.',
                    ]);
                }

                $this->deleteStoredFiles($existing);

                $existing->forceFill([
                    'document_type' => $data->documentType,
                    'document_number' => $data->documentNumber,
                    'full_legal_name' => $data->fullLegalName,
                    'nationality' => $data->nationality,
                    'date_of_birth' => $data->dateOfBirth,
                    'document_front_path' => $data->documentFrontPath,
                    'document_back_path' => $data->documentBackPath,
                    'selfie_path' => $data->selfiePath,
                    'face_match_score' => $faceMatchScore,
                    'face_match_status' => $faceMatchStatus,
                    'status' => KycSubmission::STATUS_PENDING,
                    'withdrawal_verified' => false,
                    'reviewed_by' => null,
                    'admin_notes' => null,
                    'submitted_at' => now(),
                    'reviewed_at' => null,
                ])->save();

                $this->writeAudit($existing, $user->getKey(), KycAuditLog::ACTION_RESUBMITTED, [
                    'document_type' => $data->documentType,
                    'face_match_status' => $faceMatchStatus,
                ]);

                return $existing->fresh(['user.profile', 'user.socialStatus', 'reviewer']);
            }

            $submission = KycSubmission::query()->create([
                'user_id' => $user->getKey(),
                'document_type' => $data->documentType,
                'document_number' => $data->documentNumber,
                'full_legal_name' => $data->fullLegalName,
                'nationality' => $data->nationality,
                'date_of_birth' => $data->dateOfBirth,
                'document_front_path' => $data->documentFrontPath,
                'document_back_path' => $data->documentBackPath,
                'selfie_path' => $data->selfiePath,
                'face_match_score' => $faceMatchScore,
                'face_match_status' => $faceMatchStatus,
                'status' => KycSubmission::STATUS_PENDING,
                'withdrawal_verified' => false,
                'submitted_at' => now(),
            ]);

            $this->writeAudit($submission, $user->getKey(), KycAuditLog::ACTION_SUBMITTED, [
                'document_type' => $data->documentType,
                'face_match_status' => $faceMatchStatus,
            ]);

            return $submission->fresh(['user.profile', 'user.socialStatus', 'reviewer']);
        });
    }

    public function review(KycSubmission $submission, User $reviewer, ReviewKycData $data): KycSubmission
    {
        return DB::transaction(function () use ($submission, $reviewer, $data): KycSubmission {
            $locked = KycSubmission::query()->lockForUpdate()->findOrFail($submission->getKey());

            $allowed = match ($data->status) {
                KycSubmission::STATUS_UNDER_REVIEW => [
                    KycSubmission::STATUS_PENDING,
                ],
                KycSubmission::STATUS_APPROVED => [
                    KycSubmission::STATUS_PENDING,
                    KycSubmission::STATUS_UNDER_REVIEW,
                ],
                KycSubmission::STATUS_REJECTED => [
                    KycSubmission::STATUS_PENDING,
                    KycSubmission::STATUS_UNDER_REVIEW,
                ],
                default => [],
            };

            if (! in_array($locked->status, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => 'This KYC submission cannot transition to the requested status.',
                ]);
            }

            if ($data->status === KycSubmission::STATUS_APPROVED
                && $data->faceMatchStatus === KycSubmission::FACE_MATCH_FAILED) {
                throw ValidationException::withMessages([
                    'face_match_status' => 'Cannot approve KYC when face match failed.',
                ]);
            }

            $previousStatus = $locked->status;
            $faceMatchStatus = $data->faceMatchStatus ?? $locked->face_match_status;
            $faceMatchScore = $data->faceMatchScore ?? $locked->face_match_score;
            $withdrawalVerified = $data->status === KycSubmission::STATUS_APPROVED
                ? $data->withdrawalVerified
                : false;

            $locked->forceFill([
                'status' => $data->status,
                'face_match_status' => $faceMatchStatus,
                'face_match_score' => $faceMatchScore,
                'withdrawal_verified' => $withdrawalVerified,
                'reviewed_by' => $reviewer->getKey(),
                'admin_notes' => $data->notes,
                'reviewed_at' => now(),
            ])->save();

            $action = match ($data->status) {
                KycSubmission::STATUS_UNDER_REVIEW => KycAuditLog::ACTION_UNDER_REVIEW,
                KycSubmission::STATUS_APPROVED => KycAuditLog::ACTION_APPROVED,
                KycSubmission::STATUS_REJECTED => KycAuditLog::ACTION_REJECTED,
                default => $data->status,
            };

            $this->writeAudit($locked, $reviewer->getKey(), $action, [
                'from' => $previousStatus,
                'to' => $data->status,
                'face_match_status' => $faceMatchStatus,
                'withdrawal_verified' => $withdrawalVerified,
                'notes' => $data->notes,
            ]);

            if ($faceMatchStatus === KycSubmission::FACE_MATCH_MATCHED) {
                $this->writeAudit($locked, $reviewer->getKey(), KycAuditLog::ACTION_FACE_MATCHED, [
                    'score' => $faceMatchScore,
                ]);
            }

            if ($faceMatchStatus === KycSubmission::FACE_MATCH_FAILED) {
                $this->writeAudit($locked, $reviewer->getKey(), KycAuditLog::ACTION_FACE_FAILED, [
                    'score' => $faceMatchScore,
                ]);
            }

            if ($withdrawalVerified) {
                $this->writeAudit($locked, $reviewer->getKey(), KycAuditLog::ACTION_WITHDRAWAL_VERIFIED, []);
            }

            return $locked->fresh(['user.profile', 'user.socialStatus', 'reviewer']);
        });
    }

    public function listForAdmin(?string $status, int $perPage): LengthAwarePaginator
    {
        return KycSubmission::query()
            ->with(['user.profile', 'user.socialStatus', 'reviewer'])
            ->when(
                $status !== null && $status !== '',
                fn ($query) => $query->where('status', $status),
            )
            ->orderByDesc('submitted_at')
            ->paginate($perPage);
    }

    private function deleteStoredFiles(KycSubmission $submission): void
    {
        $paths = array_filter([
            $submission->document_front_path,
            $submission->document_back_path,
            $submission->selfie_path,
        ]);

        if ($paths !== []) {
            Storage::disk((string) config('filesystems.secure_media'))->delete($paths);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function writeAudit(KycSubmission $submission, string $actorId, string $action, array $metadata): void
    {
        KycAuditLog::query()->create([
            'kyc_submission_id' => $submission->getKey(),
            'actor_id' => $actorId,
            'action' => $action,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}

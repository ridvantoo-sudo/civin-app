<?php

namespace App\Features\Kyc\Http\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Kyc\Models\KycSubmission;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KycSubmission */
final class KycSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('user') ? $this->user : null;
        $reviewer = $this->relationLoaded('reviewer') ? $this->reviewer : null;
        $isAdmin = $request->user()?->is_admin === true
            || $request->user()?->can(AdminPermission::REVIEW_KYC) === true;

        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'full_legal_name' => $this->full_legal_name,
            'nationality' => $this->nationality,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'document_front_url' => $this->documentFrontUrl(),
            'document_back_url' => $this->documentBackUrl(),
            'selfie_url' => $this->selfieUrl(),
            'face_match_score' => $this->face_match_score === null
                ? null
                : (float) $this->face_match_score,
            'face_match_status' => $this->face_match_status,
            'status' => $this->status,
            'withdrawal_verified' => (bool) $this->withdrawal_verified,
            'admin_notes' => $this->admin_notes,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'user' => $user === null ? null : new SocialUserResource($user),
            'reviewer' => $isAdmin && $reviewer !== null
                ? new SocialUserResource($reviewer)
                : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

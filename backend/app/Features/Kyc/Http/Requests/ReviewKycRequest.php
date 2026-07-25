<?php

namespace App\Features\Kyc\Http\Requests;

use App\Features\Kyc\Models\KycSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    KycSubmission::STATUS_UNDER_REVIEW,
                    KycSubmission::STATUS_APPROVED,
                    KycSubmission::STATUS_REJECTED,
                ]),
            ],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'face_match_status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(KycSubmission::faceMatchStatuses()),
            ],
            'face_match_score' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'withdrawal_verified' => ['sometimes', 'boolean'],
        ];
    }
}

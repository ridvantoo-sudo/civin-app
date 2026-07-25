<?php

namespace App\Features\Kyc\Http\Requests;

use App\Features\Kyc\Models\KycSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SubmitKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $documentType = $this->input('document_type');

        return [
            'document_type' => ['required', 'string', Rule::in(KycSubmission::documentTypes())],
            'document_number' => ['required', 'string', 'min:4', 'max:64'],
            'full_legal_name' => ['required', 'string', 'min:2', 'max:191'],
            'nationality' => ['sometimes', 'nullable', 'string', 'max:64'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'document_front' => ['required', 'file', 'image', 'max:8192', 'mimes:jpg,jpeg,png,webp'],
            'document_back' => [
                Rule::requiredIf($documentType === KycSubmission::DOCUMENT_NATIONAL_ID),
                'nullable',
                'file',
                'image',
                'max:8192',
                'mimes:jpg,jpeg,png,webp',
            ],
            'selfie' => ['required', 'file', 'image', 'max:8192', 'mimes:jpg,jpeg,png,webp'],
        ];
    }
}

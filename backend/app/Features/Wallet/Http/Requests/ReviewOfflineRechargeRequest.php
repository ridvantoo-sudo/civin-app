<?php

namespace App\Features\Wallet\Http\Requests;

use App\Features\Wallet\Models\OfflineRechargeRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewOfflineRechargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                OfflineRechargeRequest::STATUS_APPROVED,
                OfflineRechargeRequest::STATUS_REJECTED,
            ])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}

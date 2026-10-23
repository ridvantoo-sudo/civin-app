<?php

namespace App\Features\Wallet\Http\Requests;

use App\Features\Wallet\Models\WithdrawRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewWithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                WithdrawRequest::STATUS_APPROVED,
                WithdrawRequest::STATUS_REJECTED,
            ])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}

<?php

namespace App\Features\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RequestWithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'diamonds' => ['required', 'integer', 'min:1', 'max:100000000'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}

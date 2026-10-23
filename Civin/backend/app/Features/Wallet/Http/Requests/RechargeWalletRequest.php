<?php

namespace App\Features\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RechargeWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'package_id' => ['required', 'string', 'max:64'],
            'payment_provider' => ['required', 'string', 'max:64'],
            'transaction_id' => ['required', 'string', 'max:191'],
            'receipt' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}

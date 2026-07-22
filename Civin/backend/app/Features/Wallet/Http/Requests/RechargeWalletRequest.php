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
            'package_name' => ['required', 'string', 'max:120'],
            'coins' => ['required', 'integer', 'min:1', 'max:100000000'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_provider' => ['required', 'string', 'max:64'],
            'transaction_id' => ['required', 'string', 'max:191'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}

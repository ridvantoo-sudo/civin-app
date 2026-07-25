<?php

namespace App\Features\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitOfflineRechargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'coins' => ['required', 'integer', 'min:1', 'max:100000000'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'currency' => ['required', 'string', 'size:3'],
            'reference_number' => ['required', 'string', 'min:3', 'max:191'],
            'receipt' => ['required', 'file', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}

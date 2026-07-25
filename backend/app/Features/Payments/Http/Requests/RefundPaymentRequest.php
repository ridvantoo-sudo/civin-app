<?php

namespace App\Features\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RefundPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:191'],
        ];
    }
}

<?php

namespace App\Features\LiveShopping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CompleteExternalPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_provider' => ['required', 'string', 'max:64'],
            'payment_transaction_id' => ['required', 'string', 'max:191'],
        ];
    }
}

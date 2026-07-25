<?php

namespace App\Features\Payments\Http\Requests;

use App\Features\Payments\Support\PaymentGatewayKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreatePaymentIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gateway' => ['required', 'string', Rule::in(PaymentGatewayKey::all())],
            'payable_type' => ['required', 'string', 'max:191'],
            'payable_id' => ['required', 'string', 'max:191'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'return_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'token' => ['sometimes', 'nullable', 'string', 'max:8192'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'metadata.product_id' => ['sometimes', 'nullable', 'string', 'max:191'],
        ];
    }
}

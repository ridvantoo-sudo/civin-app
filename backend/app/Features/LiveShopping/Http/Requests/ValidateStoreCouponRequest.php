<?php

namespace App\Features\LiveShopping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ValidateStoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32'],
            'seller_id' => ['required', 'uuid'],
            'subtotal_coins' => ['required', 'integer', 'min:0'],
        ];
    }
}

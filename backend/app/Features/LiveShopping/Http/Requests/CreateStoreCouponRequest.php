<?php

namespace App\Features\LiveShopping\Http\Requests;

use App\Features\LiveShopping\Support\StoreCouponType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateStoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:32'],
            'type' => ['required', Rule::in(StoreCouponType::all())],
            'value' => ['required', 'integer', 'min:1'],
            'min_order_coins' => ['nullable', 'integer', 'min:0'],
            'max_discount_coins' => ['nullable', 'integer', 'min:1'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'description' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

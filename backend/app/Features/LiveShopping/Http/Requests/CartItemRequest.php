<?php

namespace App\Features\LiveShopping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'live_pin_id' => ['nullable', 'uuid'],
            'room_id' => ['nullable', 'uuid'],
        ];
    }
}

<?php

namespace App\Features\CoinSeller\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DirectSellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'buyer_id' => ['required', 'uuid', 'exists:users,id'],
            'coins' => ['required', 'integer', 'min:1', 'max:100000000'],
            'price_amount' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}

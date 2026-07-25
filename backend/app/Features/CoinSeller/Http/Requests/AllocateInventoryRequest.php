<?php

namespace App\Features\CoinSeller\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AllocateInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'coins' => ['required', 'integer', 'min:1', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

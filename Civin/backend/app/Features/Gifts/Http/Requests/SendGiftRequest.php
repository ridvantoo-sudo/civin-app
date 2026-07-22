<?php

namespace App\Features\Gifts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SendGiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'gift_id' => ['required', 'uuid', 'exists:gifts,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:999'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'client_request_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}

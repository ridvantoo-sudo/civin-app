<?php

namespace App\Features\Games\Plugins\Ludo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MovePieceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'match_id' => ['required', 'uuid'],
            'piece_index' => ['required', 'integer', 'between:0,3'],
            'metadata' => ['sometimes', 'array'],
            'to' => ['prohibited'],
        ];
    }
}

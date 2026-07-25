<?php

namespace App\Features\Games\Plugins\Ludo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class JoinLudoMatchRequest extends FormRequest
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
            'invite_code' => ['sometimes', 'nullable', 'string', 'max:16'],
            'spectator' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}

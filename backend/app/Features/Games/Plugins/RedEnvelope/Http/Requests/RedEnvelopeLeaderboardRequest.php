<?php

namespace App\Features\Games\Plugins\RedEnvelope\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RedEnvelopeLeaderboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', Rule::in(['daily', 'weekly', 'monthly', 'all'])],
            'type' => ['sometimes', 'string', Rule::in(['winners', 'senders', 'receivers'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

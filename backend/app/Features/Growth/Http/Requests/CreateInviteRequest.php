<?php

namespace App\Features\Growth\Http\Requests;

use App\Features\Growth\Models\Invite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['sometimes', 'string', Rule::in([Invite::CHANNEL_LINK, Invite::CHANNEL_EMAIL])],
            'target' => ['nullable', 'email', 'max:255'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}

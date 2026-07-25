<?php

namespace App\Features\SocialEngagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LogMentorshipSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'held_at' => ['nullable', 'date'],
        ];
    }
}

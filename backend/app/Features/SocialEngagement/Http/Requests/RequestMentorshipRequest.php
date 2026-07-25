<?php

namespace App\Features\SocialEngagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RequestMentorshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'target_user_id' => ['required', 'uuid'],
            'role' => ['required', 'in:mentor,mentee'],
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }
}

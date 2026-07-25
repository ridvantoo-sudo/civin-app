<?php

namespace App\Features\Challenges\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ClaimChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'uuid', 'exists:challenges,id'],
        ];
    }
}

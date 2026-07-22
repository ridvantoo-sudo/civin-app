<?php

namespace App\Features\Reports\Http\Requests;

use App\Features\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateReportRequest extends FormRequest
{
    public const CATEGORIES = [
        'spam',
        'harassment',
        'hate_speech',
        'impersonation',
        'nudity',
        'violence',
        'other',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->route('user');
        $this->merge(['user_id' => $user instanceof User ? $user->getKey() : $user]);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'details' => ['nullable', 'string', 'max:5000', 'required_if:category,other'],
        ];
    }
}

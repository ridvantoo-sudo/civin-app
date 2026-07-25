<?php

namespace App\Features\Security\Requests;

use App\Features\Security\Support\SecuritySurface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SecurityReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reported_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'category' => ['required', 'string', Rule::in([
                'cheating', 'bot', 'fraud', 'harassment', 'impersonation', 'spam', 'other',
            ])],
            'surface' => ['nullable', 'string', Rule::in(SecuritySurface::all())],
            'subject_id' => ['nullable', 'string', 'max:96'],
            'reason' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

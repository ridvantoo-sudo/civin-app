<?php

namespace App\Features\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TranslateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:5000'],
            'target_locale' => ['required', 'string', 'max:16'],
            'source_locale' => ['nullable', 'string', 'max:16'],
        ];
    }
}

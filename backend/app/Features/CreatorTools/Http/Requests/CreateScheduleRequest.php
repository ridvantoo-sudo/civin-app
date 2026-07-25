<?php

namespace App\Features\CreatorTools\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:live_categories,id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'thumbnail' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}

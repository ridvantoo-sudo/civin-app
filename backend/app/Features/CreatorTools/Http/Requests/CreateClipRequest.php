<?php

namespace App\Features\CreatorTools\Http\Requests;

use App\Features\CreatorTools\Support\ClipKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateClipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'start_seconds' => ['required', 'integer', 'min:0'],
            'end_seconds' => ['required', 'integer', 'gt:start_seconds'],
            'kind' => ['sometimes', 'string', Rule::in(ClipKind::all())],
            'thumbnail_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}

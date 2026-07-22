<?php

namespace App\Features\LiveStreaming\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateLiveRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('live_categories', 'id')->where('status', 'active'),
            ],
            'title' => ['required', 'string', 'min:2', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'thumbnail' => [
                'nullable',
                'string',
                'max:2048',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    $validUrl = filter_var($value, FILTER_VALIDATE_URL) !== false
                        && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);
                    $validPath = str_starts_with($value, '/') && ! str_contains($value, '..');

                    if (! $validUrl && ! $validPath) {
                        $fail("The {$attribute} must be an HTTP(S) URL or an absolute application path.");
                    }
                },
            ],
        ];
    }
}

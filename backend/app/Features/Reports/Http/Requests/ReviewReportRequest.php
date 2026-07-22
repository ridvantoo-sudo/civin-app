<?php

namespace App\Features\Reports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('report')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['reviewing', 'resolved', 'dismissed'])],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

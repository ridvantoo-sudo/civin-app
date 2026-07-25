<?php

namespace App\Features\Levels\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ClaimDailyTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'task_id' => ['required', 'uuid', 'exists:daily_tasks,id'],
        ];
    }
}

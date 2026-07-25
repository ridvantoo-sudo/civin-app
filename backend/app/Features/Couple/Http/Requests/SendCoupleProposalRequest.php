<?php

namespace App\Features\Couple\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SendCoupleProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'message' => ['nullable', 'string', 'max:500'],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ];
    }
}

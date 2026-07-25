<?php

namespace App\Features\FamilyClub\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReviewFamilyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
        ];
    }
}

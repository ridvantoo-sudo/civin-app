<?php

namespace App\Features\FamilyClub\Http\Requests;

use App\Features\FamilyClub\Models\FamilyMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InviteFamilyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'role' => ['nullable', 'string', Rule::in(FamilyMember::assignableRoles())],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ];
    }
}

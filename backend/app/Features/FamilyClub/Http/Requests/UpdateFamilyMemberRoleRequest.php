<?php

namespace App\Features\FamilyClub\Http\Requests;

use App\Features\FamilyClub\Models\FamilyMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateFamilyMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role' => ['required', 'string', Rule::in(FamilyMember::assignableRoles())],
        ];
    }
}

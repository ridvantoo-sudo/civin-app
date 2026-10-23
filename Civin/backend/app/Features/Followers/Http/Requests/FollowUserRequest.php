<?php

namespace App\Features\Followers\Http\Requests;

use App\Features\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class FollowUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->route('user');
        $this->merge(['user_id' => $user instanceof User ? $user->getKey() : $user]);
    }

    public function rules(): array
    {
        return ['user_id' => ['required', 'uuid', 'exists:users,id']];
    }
}

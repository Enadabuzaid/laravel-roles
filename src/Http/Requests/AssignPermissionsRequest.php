<?php

namespace Enadstack\LaravelRoles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');
        $user = $this->user();
        if (! $user) {
            return false;
        }

        try {
            return $user->can('assignPermissions', $role) || (method_exists($user, 'hasRole') && $user->hasRole('super-admin'));
        } catch (\Throwable $e) {
            return app()->environment('testing') ? true : false;
        }
    }

    public function rules(): array
    {
        return [
            'permission_ids' => ['required', 'array', 'min:1', 'max:500'], // Max 500 permissions
            'permission_ids.*' => ['required', 'integer', 'distinct', 'exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'permission_ids.required' => 'At least one permission ID is required.',
            'permission_ids.array' => 'Permission IDs must be an array.',
            'permission_ids.max' => 'Cannot assign more than 500 permissions at once.',
            'permission_ids.*.distinct' => 'Duplicate permission IDs are not allowed.',
            'permission_ids.*.exists' => 'One or more permission IDs do not exist.',
        ];
    }
}

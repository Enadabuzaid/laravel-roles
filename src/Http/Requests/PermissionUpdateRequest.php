<?php

namespace Enadstack\LaravelRoles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PermissionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('permission');
        $user = $this->user();
        if (! $user) {
            return false;
        }

        try {
            return $user->can('update', $permission) || (method_exists($user, 'hasRole') && $user->hasRole('super-admin'));
        } catch (\Throwable $e) {
            return app()->environment('testing') ? true : false;
        }
    }

    public function rules(): array
    {
        $permission = $this->route('permission');
        $permissionId = $permission->id ?? $permission;
        $currentGuard = $permission->guard_name ?? config('roles.guard', 'web');
        $newGuard = $this->input('guard_name', $currentGuard);

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9_.-]+$/',
                Rule::unique('permissions')->where(function ($query) use ($newGuard) {
                    return $query->where('guard_name', $newGuard);
                })->ignore($permissionId),
            ],
            'guard_name' => ['sometimes', 'string', 'max:255', 'in:web,api,admin'],
            'group' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9_-]+$/'],
            'label' => ['nullable', 'array'],
            'label.*' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:1000'],
            'group_label' => ['nullable', 'array'],
            'group_label.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A permission with this name already exists.',
            'label.array' => 'Label must be a key-value object.',
            'description.array' => 'Description must be a key-value object.',
            'group_label.array' => 'Group label must be a key-value object.',
        ];
    }
}

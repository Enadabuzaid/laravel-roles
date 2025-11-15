<?php

namespace Enadstack\LaravelRoles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PermissionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionId = $this->route('permission')->id ?? $this->route('permission');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')->ignore($permissionId),
            ],
            'guard_name' => ['sometimes', 'string', 'max:255'],
            'group' => ['nullable', 'string', 'max:255'],
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


<?php

namespace Enadstack\LaravelRoles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permission_ids' => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['required', 'integer', 'exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'permission_ids.required' => 'At least one permission ID is required.',
            'permission_ids.array' => 'Permission IDs must be an array.',
            'permission_ids.*.exists' => 'One or more permission IDs do not exist.',
        ];
    }
}


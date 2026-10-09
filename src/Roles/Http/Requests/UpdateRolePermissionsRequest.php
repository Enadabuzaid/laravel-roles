<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Requests;

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', Role::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'grant' => PermissionReferenceRules::list(),
            'revoke' => PermissionReferenceRules::list(),
            'confirm' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<int|string> */
    public function grant(): array
    {
        return PermissionReferenceRules::normalize($this->validated('grant', []));
    }

    /** @return list<int|string> */
    public function revoke(): array
    {
        return PermissionReferenceRules::normalize($this->validated('revoke', []));
    }

    public function confirmed(): bool
    {
        return $this->boolean('confirm');
    }
}

<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Requests;

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class AssignRoleRequest extends UserRoleRequest
{
    use ResolvesRoleReferences;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => ['required', fn (string $attribute, mixed $value, \Closure $fail) => is_string($value) || is_int($value) ? null : $fail('The role must be a role name or id.')],
            'confirm' => ['sometimes', 'boolean'],
        ];
    }

    /** @throws ModelNotFoundException */
    public function roleToAssign(): Role
    {
        return $this->findRole($this->validated('role')) ?? throw (new ModelNotFoundException)->setModel(Role::class);
    }

    public function confirmed(): bool
    {
        return $this->boolean('confirm');
    }
}

<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Http\Requests\ResolvesRoleReferences;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class StoreInvitationRequest extends InvitationRequest
{
    use ResolvesRoleReferences;

    protected function ability(): string
    {
        return 'create';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'role' => ['required', fn (string $attribute, mixed $value, \Closure $fail) => is_string($value) || is_int($value) ? null : $fail('The role must be a role name or id.')],
            'confirm' => ['sometimes', 'boolean'],
        ];
    }

    public function email(): string
    {
        return (string) $this->validated('email');
    }

    /** @throws ModelNotFoundException */
    public function roleToInvite(): Role
    {
        return $this->findRole($this->validated('role')) ?? throw (new ModelNotFoundException)->setModel(Role::class);
    }

    public function confirmed(): bool
    {
        return $this->boolean('confirm');
    }
}

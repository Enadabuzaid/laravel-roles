<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Requests;

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Validation\Validator;

final class SyncUserRolesRequest extends UserRoleRequest
{
    use ResolvesRoleReferences;

    /** @var array<int, Role> */
    private array $resolved = [];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'roles' => ['present', 'array'],
            'roles.*' => [fn (string $attribute, mixed $value, \Closure $fail) => is_string($value) || is_int($value) ? null : $fail('Each role must be a role name or id.')],
            'confirm' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var list<mixed> $references */
            $references = array_values((array) $this->input('roles', []));

            foreach ($references as $index => $reference) {
                $role = $this->findRole($reference);

                if ($role === null) {
                    $validator->errors()->add("roles.{$index}", 'The selected role does not exist.');

                    continue;
                }

                $this->resolved[(int) $role->getKey()] = $role;
            }
        }];
    }

    /** @return list<Role> */
    public function rolesToSync(): array
    {
        return array_values($this->resolved);
    }

    public function confirmed(): bool
    {
        return $this->boolean('confirm');
    }
}

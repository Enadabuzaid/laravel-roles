<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Requests;

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Support\Facades\Gate;

final class UpdateRoleRequest extends StoreRoleRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', Role::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $role = $this->route('role');

        return $this->roleRules(partial: true, ignoreId: $role instanceof Role ? (int) $role->getKey() : null);
    }
}

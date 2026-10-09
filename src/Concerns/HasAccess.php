<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Concerns;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Actions\AssignRoleToUser;
use Enadstack\LaravelRoles\Roles\Actions\RemoveRoleFromUser;
use Enadstack\LaravelRoles\Roles\Actions\SyncUserRoles;
use Spatie\Permission\Traits\HasRoles;

trait HasAccess
{
    use HasRoles;

    public function assignAccessRole(string|Role $role, bool $confirmed = false): static
    {
        app(AssignRoleToUser::class)->handle($this, $role, $confirmed);

        return $this;
    }

    public function removeAccessRole(string|Role $role): static
    {
        app(RemoveRoleFromUser::class)->handle($this, $role);

        return $this;
    }

    /** @param  list<string|Role>  $roles */
    public function syncAccessRoles(array $roles, bool $confirmed = false): static
    {
        app(SyncUserRoles::class)->handle($this, $roles, $confirmed);

        return $this;
    }

    public function isOwner(): bool
    {
        return $this->hasRole((string) config('roles.super_role'));
    }
}

<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Policies;

final class RolePolicy
{
    public function viewAny(mixed $user): bool
    {
        return $this->allows($user, 'access.roles.view');
    }

    public function view(mixed $user): bool
    {
        return $this->allows($user, 'access.roles.view');
    }

    public function create(mixed $user): bool
    {
        return $this->allows($user, 'access.roles.create');
    }

    public function update(mixed $user): bool
    {
        return $this->allows($user, 'access.roles.update');
    }

    public function delete(mixed $user): bool
    {
        return $this->allows($user, 'access.roles.delete');
    }

    public function restore(mixed $user): bool
    {
        return $this->allows($user, 'access.roles.delete');
    }

    public function forceDelete(mixed $user): bool
    {
        return $this->allows($user, 'access.roles.delete');
    }

    public function assign(mixed $user): bool
    {
        return $this->allows($user, 'access.roles.assign');
    }

    private function allows(mixed $user, string $permission): bool
    {
        return is_object($user) && method_exists($user, 'checkPermissionTo') && $user->checkPermissionTo($permission);
    }
}

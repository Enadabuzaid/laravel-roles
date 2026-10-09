<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Policies;

final class PermissionPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $this->allows($user, 'access.permissions.view');
    }

    public function view(mixed $user): bool
    {
        return $this->allows($user, 'access.permissions.view');
    }

    public function create(mixed $user): bool
    {
        return $this->allows($user, 'access.permissions.sync');
    }

    public function update(mixed $user): bool
    {
        return $this->allows($user, 'access.permissions.sync');
    }

    public function delete(mixed $user): bool
    {
        return $this->allows($user, 'access.permissions.sync');
    }

    public function sync(mixed $user): bool
    {
        return $this->allows($user, 'access.permissions.sync');
    }

    private function allows(mixed $user, string $permission): bool
    {
        return is_object($user) && method_exists($user, 'checkPermissionTo') && $user->checkPermissionTo($permission);
    }
}

<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Policies;

/** Maps user abilities to the package permissions. Registered for `roles.user_model` only when the host defines no policy for it. */
final class UserPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $this->allows($user, 'access.users.view');
    }

    public function view(mixed $user): bool
    {
        return $this->allows($user, 'access.users.view');
    }

    public function create(mixed $user): bool
    {
        return $this->allows($user, 'access.users.create');
    }

    public function update(mixed $user): bool
    {
        return $this->allows($user, 'access.users.update');
    }

    public function suspend(mixed $user): bool
    {
        return $this->allows($user, 'access.users.suspend');
    }

    public function delete(mixed $user): bool
    {
        return $this->allows($user, 'access.users.delete');
    }

    private function allows(mixed $user, string $permission): bool
    {
        return is_object($user) && method_exists($user, 'checkPermissionTo') && $user->checkPermissionTo($permission);
    }
}

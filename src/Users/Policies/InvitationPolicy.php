<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Policies;

/** Maps invitation abilities to the package permissions. */
final class InvitationPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $this->allows($user, 'access.invitations.view');
    }

    public function create(mixed $user): bool
    {
        return $this->allows($user, 'access.invitations.create');
    }

    public function revoke(mixed $user): bool
    {
        return $this->allows($user, 'access.invitations.revoke');
    }

    private function allows(mixed $user, string $permission): bool
    {
        return is_object($user) && method_exists($user, 'checkPermissionTo') && $user->checkPermissionTo($permission);
    }
}

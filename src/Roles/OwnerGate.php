<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles;

use Enadstack\LaravelRoles\Contracts\AccessUser;

/**
 * Gate::before callback: the owner (super role) passes every check; everyone else
 * returns null so the normal gate and permission checks decide.
 */
final class OwnerGate
{
    public function __invoke(mixed $user): ?bool
    {
        $super = config('roles.super_role');

        return is_string($super) && $super !== '' && $user instanceof AccessUser && $user->hasRole($super) ? true : null;
    }
}

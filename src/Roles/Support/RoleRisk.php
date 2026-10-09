<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Support;

use Enadstack\LaravelRoles\Models\Role;

final class RoleRisk
{
    public static function isOwnerRole(Role $role): bool
    {
        return $role->name === (string) config('roles.super_role');
    }

    /** @return 'owner'|'high'|'normal' */
    public static function level(Role $role): string
    {
        if (self::isOwnerRole($role)) {
            return 'owner';
        }

        $role->loadMissing('permissions');

        return $role->permissions->contains('risk', 'high') ? 'high' : 'normal';
    }
}

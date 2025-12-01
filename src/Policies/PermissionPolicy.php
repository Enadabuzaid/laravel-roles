<?php

namespace Enadstack\LaravelRoles\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;
use Enadstack\LaravelRoles\Models\Permission;

class PermissionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any permissions.
     */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('permissions.list') || $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can view the permission.
     */
    public function view(Authenticatable $user, Permission $permission): bool
    {
        return $user->can('permissions.show') || $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can create permissions.
     */
    public function create(Authenticatable $user): bool
    {
        return $user->can('permissions.create') || $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can update the permission.
     */
    public function update(Authenticatable $user, Permission $permission): bool
    {
        return $user->can('permissions.update') || $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can delete the permission.
     */
    public function delete(Authenticatable $user, Permission $permission): bool
    {
        return $user->can('permissions.delete') || $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can restore the permission.
     */
    public function restore(Authenticatable $user): bool
    {
        return $user->can('permissions.restore') || $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can permanently delete the permission.
     */
    public function forceDelete(Authenticatable $user, Permission $permission): bool
    {
        // Only super-admin can force delete
        return $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can perform bulk operations.
     */
    public function bulkDelete(Authenticatable $user): bool
    {
        return $user->can('permissions.bulk-delete') || $user->hasRole('super-admin');
    }
}


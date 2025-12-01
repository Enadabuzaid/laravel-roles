<?php

namespace Enadstack\LaravelRoles\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;
use Enadstack\LaravelRoles\Models\Role;

class RolePolicy
{
    use HandlesAuthorization;

    protected function inSameTeam(Role $role): bool
    {
        if (config('roles.tenancy.mode') !== 'team_scoped') {
            return true;
        }

        $teamKey = config('roles.tenancy.team_foreign_key', 'team_id');
        $currentTeam = app()->bound('permission.team_id') ? app('permission.team_id') : null;

        if (is_null($currentTeam)) {
            return false;
        }

        return isset($role->{$teamKey}) && (string) $role->{$teamKey} === (string) $currentTeam;
    }

    /**
     * Determine if the user can view any roles.
     *
     * @param Authenticatable|\Spatie\Permission\Traits\HasRoles $user
     */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('roles.list') || $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can view the role.
     *
     * @param Authenticatable|\Spatie\Permission\Traits\HasRoles $user
     */
    public function view(Authenticatable $user, Role $role): bool
    {
        if (! $this->inSameTeam($role)) {
            return false;
        }

        return $user->can('roles.show') || $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can create roles.
     *
     * @param Authenticatable|\Spatie\Permission\Traits\HasRoles $user
     */
    public function create(Authenticatable $user): bool
    {
        return $user->can('roles.create') || $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can update the role.
     *
     * @param Authenticatable|\Spatie\Permission\Traits\HasRoles $user
     */
    public function update(Authenticatable $user, Role $role): bool
    {
        if (! $this->inSameTeam($role)) {
            return false;
        }

        // Protect system roles
        if (in_array($role->name, ['super-admin', 'admin'], true)) {
            return $user->hasRole('super-admin');
        }

        return $user->can('roles.update') || $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can delete the role.
     *
     * @param Authenticatable|\Spatie\Permission\Traits\HasRoles $user
     */
    public function delete(Authenticatable $user, Role $role): bool
    {
        if (! $this->inSameTeam($role)) {
            return false;
        }

        // Protect system roles
        if (in_array($role->name, ['super-admin', 'admin', 'user'], true)) {
            return false; // Never allow deleting system roles
        }

        return $user->can('roles.delete') || $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can restore the role.
     *
     * @param Authenticatable|\Spatie\Permission\Traits\HasRoles $user
     */
    public function restore(Authenticatable $user, Role $role): bool
    {
        if (! $this->inSameTeam($role)) {
            return false;
        }

        return $user->can('roles.restore') || $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can permanently delete the role.
     *
     * @param Authenticatable|\Spatie\Permission\Traits\HasRoles $user
     */
    public function forceDelete(Authenticatable $user, Role $role): bool
    {
        if (! $this->inSameTeam($role)) {
            return false;
        }

        // Only super-admin can force delete, and never system roles
        if (in_array($role->name, ['super-admin', 'admin', 'user'], true)) {
            return false;
        }

        return $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can perform bulk operations.
     *
     * @param Authenticatable|\Spatie\Permission\Traits\HasRoles $user
     */
    public function bulkDelete(Authenticatable $user): bool
    {
        return $user->can('roles.bulk-delete') || $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can assign permissions to roles.
     *
     * @param Authenticatable|\Spatie\Permission\Traits\HasRoles $user
     */
    public function assignPermissions(Authenticatable $user, Role $role): bool
    {
        if (! $this->inSameTeam($role)) {
            return false;
        }

        // Protect super-admin role
        if ($role->name === 'super-admin') {
            return $user->hasRole('super-admin');
        }

        return $user->can('roles.assign-permissions') || $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can clone roles.
     *
     * @param Authenticatable|\Spatie\Permission\Traits\HasRoles $user
     */
    public function clone(Authenticatable $user, Role $role): bool
    {
        if (! $this->inSameTeam($role)) {
            return false;
        }

        // Don't allow cloning super-admin
        if ($role->name === 'super-admin') {
            return false;
        }

        return $user->can('roles.clone') || $user->hasRole('super-admin');
    }
}

<?php

namespace Enadstack\LaravelRoles\Observers;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum;

class RoleObserver
{
    /**
     * Handle the Role "creating" event.
     */
    public function creating(Role $role): void
    {
        // Set default status if not set
        if (!isset($role->status)) {
            $role->status = RolePermissionStatusEnum::ACTIVE->value;
        }
    }

    /**
     * Handle the Role "deleting" event.
     */
    public function deleting(Role $role): void
    {
        // Change status to deleted when soft deleting
        if (!$role->isForceDeleting()) {
            $role->status = RolePermissionStatusEnum::DELETED->value;
            $role->saveQuietly(); // Save without triggering events again
        }
    }

    /**
     * Handle the Role "restoring" event.
     */
    public function restoring(Role $role): void
    {
        // Change status back to active when restoring
        $role->status = RolePermissionStatusEnum::ACTIVE->value;
    }

    /**
     * Handle the Role "force deleted" event.
     */
    public function forceDeleted(Role $role): void
    {
        // Additional cleanup if needed
    }
}


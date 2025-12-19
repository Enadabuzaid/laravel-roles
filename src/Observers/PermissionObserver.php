<?php

namespace Enadstack\LaravelRoles\Observers;

use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum;

class PermissionObserver
{
    /**
     * Handle the Permission "creating" event.
     */
    public function creating(Permission $permission): void
    {
        // Set default status if not set
        if (!isset($permission->status)) {
            $permission->status = RolePermissionStatusEnum::ACTIVE->value;
        }
    }

    /**
     * Handle the Permission "deleting" event.
     */
    public function deleting(Permission $permission): void
    {
        // Change status to deleted when soft deleting
        if (!$permission->isForceDeleting()) {
            $permission->status = RolePermissionStatusEnum::DELETED->value;
            $permission->saveQuietly(); // Save without triggering events again
        }
    }

    /**
     * Handle the Permission "restoring" event.
     */
    public function restoring(Permission $permission): void
    {
        // Change status back to active when restoring
        $permission->status = RolePermissionStatusEnum::ACTIVE->value;
    }

    /**
     * Handle the Permission "force deleted" event.
     */
    public function forceDeleted(Permission $permission): void
    {
        // Additional cleanup if needed
    }
}


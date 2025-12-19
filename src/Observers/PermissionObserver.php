<?php

namespace Enadstack\LaravelRoles\Observers;

use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum;
use Illuminate\Support\Facades\Cache;

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
     * Handle the Permission "created" event.
     */
    public function created(Permission $permission): void
    {
        $this->flushCaches();
    }

    /**
     * Handle the Permission "updated" event.
     */
    public function updated(Permission $permission): void
    {
        $this->flushCaches();
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
     * Handle the Permission "deleted" event.
     */
    public function deleted(Permission $permission): void
    {
        $this->flushCaches();
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
     * Handle the Permission "restored" event.
     */
    public function restored(Permission $permission): void
    {
        $this->flushCaches();
    }

    /**
     * Handle the Permission "force deleted" event.
     */
    public function forceDeleted(Permission $permission): void
    {
        $this->flushCaches();
    }

    /**
     * Flush package caches to ensure stats are accurate
     */
    protected function flushCaches(): void
    {
        $store = Cache::getStore();
        if (method_exists($store, 'tags')) {
            Cache::tags(['laravel_roles'])->flush();
        } else {
            Cache::forget(config('roles.cache.keys.grouped_permissions', 'laravel_roles.grouped_permissions'));
            Cache::forget(config('roles.cache.keys.permission_matrix', 'laravel_roles.permission_matrix'));
        }
    }
}


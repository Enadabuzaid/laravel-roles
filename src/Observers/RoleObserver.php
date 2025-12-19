<?php

namespace Enadstack\LaravelRoles\Observers;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum;
use Illuminate\Support\Facades\Cache;

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
     * Handle the Role "created" event.
     */
    public function created(Role $role): void
    {
        $this->flushCaches();
    }

    /**
     * Handle the Role "updated" event.
     */
    public function updated(Role $role): void
    {
        $this->flushCaches();
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
     * Handle the Role "deleted" event.
     */
    public function deleted(Role $role): void
    {
        $this->flushCaches();
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
     * Handle the Role "restored" event.
     */
    public function restored(Role $role): void
    {
        $this->flushCaches();
    }

    /**
     * Handle the Role "force deleted" event.
     */
    public function forceDeleted(Role $role): void
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


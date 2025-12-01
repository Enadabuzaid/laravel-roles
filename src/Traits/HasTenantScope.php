<?php

namespace Enadstack\LaravelRoles\Traits;

use Enadstack\LaravelRoles\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * HasTenantScope Trait
 *
 * Provides automatic tenant scoping for models in team_scoped mode.
 *
 * Features:
 * - Auto-applies TenantScope to all queries
 * - Auto-sets tenant FK on model creation
 * - Provides scope methods for cross-tenant queries
 *
 * Usage:
 *
 * ```php
 * class Role extends Model
 * {
 *     use HasTenantScope;
 * }
 *
 * // Standard query (scoped to current tenant + global)
 * $roles = Role::all();
 *
 * // Query all tenants (super-admin only)
 * $allRoles = Role::forAllTenants()->get();
 *
 * // Query only tenant-specific (exclude global)
 * $tenantRoles = Role::onlyTenantSpecific()->get();
 *
 * // Query only global records
 * $globalRoles = Role::onlyGlobal()->get();
 * ```
 */
trait HasTenantScope
{
    /**
     * Boot the HasTenantScope trait for a model.
     */
    protected static function bootHasTenantScope(): void
    {
        // Apply global scope to all queries
        static::addGlobalScope(new TenantScope());

        // Auto-set tenant FK when creating new records
        static::creating(function ($model) {
            // Only apply in team_scoped mode
            if (config('roles.tenancy.mode') !== 'team_scoped') {
                return;
            }

            $fk = config('permission.team_foreign_key', 'team_id');

            // Only set if:
            // 1. FK is not already set
            // 2. Tenant context is available
            if (!isset($model->$fk) && app()->bound('permission.team_id')) {
                $model->$fk = app('permission.team_id');
            }
        });
    }

    /**
     * Query across all tenants (bypass tenant scope)
     *
     * Useful for super-admin queries or reporting across tenants.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeForAllTenants(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Query only tenant-specific records (exclude global records)
     *
     * Returns only records that have a tenant_id set (not NULL).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOnlyTenantSpecific(Builder $query): Builder
    {
        $fk = config('permission.team_foreign_key', 'team_id');

        return $query->withoutGlobalScope(TenantScope::class)
            ->whereNotNull($fk);
    }

    /**
     * Query only global records (no tenant)
     *
     * Returns only records where tenant_id is NULL.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOnlyGlobal(Builder $query): Builder
    {
        $fk = config('permission.team_foreign_key', 'team_id');

        return $query->withoutGlobalScope(TenantScope::class)
            ->whereNull($fk);
    }

    /**
     * Query for a specific tenant (different from current)
     *
     * Useful for tenant impersonation or cross-tenant operations.
     *
     * @param Builder $query
     * @param int|string|null $tenantId
     * @return Builder
     */
    public function scopeForTenant(Builder $query, int|string|null $tenantId): Builder
    {
        $fk = config('permission.team_foreign_key', 'team_id');

        return $query->withoutGlobalScope(TenantScope::class)
            ->where(function ($q) use ($fk, $tenantId) {
                $q->whereNull($fk)->orWhere($fk, $tenantId);
            });
    }

    /**
     * Check if this record is global (not tenant-specific)
     *
     * @return bool
     */
    public function isGlobal(): bool
    {
        $fk = config('permission.team_foreign_key', 'team_id');
        return is_null($this->$fk);
    }

    /**
     * Check if this record belongs to a specific tenant
     *
     * @param int|string|null $tenantId
     * @return bool
     */
    public function belongsToTenant(int|string|null $tenantId): bool
    {
        $fk = config('permission.team_foreign_key', 'team_id');
        return $this->$fk == $tenantId;
    }

    /**
     * Check if this record belongs to the current tenant context
     *
     * @return bool
     */
    public function belongsToCurrentTenant(): bool
    {
        if (!app()->bound('permission.team_id')) {
            return false;
        }

        return $this->belongsToTenant(app('permission.team_id'));
    }
}


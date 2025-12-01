<?php

namespace Enadstack\LaravelRoles\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Tenant Scope for Team Scoped Mode
 *
 * Automatically filters queries to only return:
 * - Global records (tenant_id/team_id = NULL)
 * - Records belonging to the current tenant
 *
 * Usage:
 * - Applied automatically via HasTenantScope trait
 * - Bypass with: Model::forAllTenants()->get()
 * - Only tenant-specific: Model::onlyTenantSpecific()->get()
 * - Only global: Model::onlyGlobal()->get()
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply if team_scoped mode is enabled
        if (config('roles.tenancy.mode') !== 'team_scoped') {
            return;
        }

        // Only apply if tenant context is set
        if (!app()->bound('permission.team_id')) {
            return;
        }

        $tenantId = app('permission.team_id');
        $fk = config('permission.team_foreign_key', 'team_id');

        // Filter: (tenant_id IS NULL OR tenant_id = current_tenant)
        $builder->where(function ($query) use ($fk, $tenantId) {
            $query->whereNull($fk)
                ->orWhere($fk, $tenantId);
        });

        // Order by tenant-specific first (tenant_id IS NOT NULL)
        // This ensures tenant-specific records take priority over global ones
        $builder->orderByRaw("CASE WHEN {$fk} IS NULL THEN 1 ELSE 0 END");
    }
}


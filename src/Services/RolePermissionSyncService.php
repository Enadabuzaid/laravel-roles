<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Services;

use Enadstack\LaravelRoles\Contracts\RolePermissionSyncServiceContract;
use Enadstack\LaravelRoles\Contracts\TenantContextContract;
use Enadstack\LaravelRoles\Contracts\GuardResolverContract;
use Enadstack\LaravelRoles\Contracts\CacheKeyBuilderContract;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Events\PermissionsAssignedToRole;
use Illuminate\Support\Facades\DB;

/**
 * RolePermissionSyncService
 *
 * Handles syncing permissions to roles with diff-based updates
 * and wildcard expansion support.
 *
 * @package Enadstack\LaravelRoles\Services
 */
class RolePermissionSyncService implements RolePermissionSyncServiceContract
{
    /**
     * Tenant context instance.
     *
     * @var TenantContextContract
     */
    protected TenantContextContract $tenantContext;

    /**
     * Guard resolver instance.
     *
     * @var GuardResolverContract
     */
    protected GuardResolverContract $guardResolver;

    /**
     * Cache key builder instance.
     *
     * @var CacheKeyBuilderContract
     */
    protected CacheKeyBuilderContract $cacheKeyBuilder;

    /**
     * Create a new service instance.
     *
     * @param TenantContextContract $tenantContext
     * @param GuardResolverContract $guardResolver
     * @param CacheKeyBuilderContract $cacheKeyBuilder
     */
    public function __construct(
        TenantContextContract $tenantContext,
        GuardResolverContract $guardResolver,
        CacheKeyBuilderContract $cacheKeyBuilder
    ) {
        $this->tenantContext = $tenantContext;
        $this->guardResolver = $guardResolver;
        $this->cacheKeyBuilder = $cacheKeyBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function assignPermissions(Role $role, array $permissionIds): Role
    {
        $permissions = Permission::whereIn('id', $permissionIds)
            ->where('guard_name', $role->guard_name)
            ->get();

        $role->syncPermissions($permissions);
        $this->invalidateCaches();

        event(new PermissionsAssignedToRole($role, $permissionIds));

        return $role->refresh();
    }

    /**
     * {@inheritdoc}
     */
    public function diffSync(Role $role, array $grant = [], array $revoke = []): array
    {
        $result = [
            'granted' => [],
            'revoked' => [],
            'skipped' => [],
        ];

        $guard = $role->guard_name;

        // Expand wildcards
        $grantExpanded = $this->expandWildcards($grant, $guard);
        $revokeExpanded = $this->expandWildcards($revoke, $guard);

        // Get current permissions
        $currentPermissions = $role->permissions->pluck('name')->toArray();

        DB::transaction(function () use ($role, $grantExpanded, $revokeExpanded, $currentPermissions, $guard, &$result) {
            // Process grants
            foreach ($grantExpanded as $permissionName) {
                // Skip if already has permission
                if (in_array($permissionName, $currentPermissions, true)) {
                    $result['skipped'][] = [
                        'permission' => $permissionName,
                        'reason' => 'already_granted',
                    ];
                    continue;
                }

                $permission = Permission::where('name', $permissionName)
                    ->where('guard_name', $guard)
                    ->first();

                if ($permission) {
                    $role->givePermissionTo($permission);
                    $result['granted'][] = $permissionName;
                } else {
                    $result['skipped'][] = [
                        'permission' => $permissionName,
                        'reason' => 'not_found',
                    ];
                }
            }

            // Process revokes
            foreach ($revokeExpanded as $permissionName) {
                // Skip if doesn't have permission
                if (!in_array($permissionName, $currentPermissions, true)) {
                    $result['skipped'][] = [
                        'permission' => $permissionName,
                        'reason' => 'not_assigned',
                    ];
                    continue;
                }

                $permission = Permission::where('name', $permissionName)
                    ->where('guard_name', $guard)
                    ->first();

                if ($permission) {
                    $role->revokePermissionTo($permission);
                    $result['revoked'][] = $permissionName;
                }
            }
        });

        $this->invalidateCaches();

        // Fire event if any changes were made
        if (!empty($result['granted']) || !empty($result['revoked'])) {
            $permissionIds = $role->refresh()->permissions->pluck('id')->toArray();
            event(new PermissionsAssignedToRole($role, $permissionIds));
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function expandWildcards(array $patterns, ?string $guard = null): array
    {
        $guard = $guard ?? $this->guardResolver->guard();
        $expanded = [];

        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                // All permissions for this guard
                $expanded = array_merge(
                    $expanded,
                    Permission::where('guard_name', $guard)->pluck('name')->toArray()
                );
            } elseif (str_ends_with($pattern, '.*')) {
                // Group wildcard (e.g., 'users.*')
                $group = substr($pattern, 0, -2);
                $groupPermissions = Permission::where('guard_name', $guard)
                    ->where('name', 'like', $group . '.%')
                    ->pluck('name')
                    ->toArray();
                $expanded = array_merge($expanded, $groupPermissions);
            } else {
                // Specific permission
                $expanded[] = $pattern;
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * {@inheritdoc}
     */
    public function addPermission(Role $role, int|string $permission): Role
    {
        $perm = is_int($permission)
            ? Permission::findOrFail($permission)
            : Permission::where('name', $permission)
                ->where('guard_name', $role->guard_name)
                ->firstOrFail();

        $role->givePermissionTo($perm);
        $this->invalidateCaches();

        return $role->refresh()->load('permissions');
    }

    /**
     * {@inheritdoc}
     */
    public function removePermission(Role $role, int|string $permission): Role
    {
        $perm = is_int($permission)
            ? Permission::findOrFail($permission)
            : Permission::where('name', $permission)
                ->where('guard_name', $role->guard_name)
                ->firstOrFail();

        $role->revokePermissionTo($perm);
        $this->invalidateCaches();

        return $role->refresh()->load('permissions');
    }

    /**
     * {@inheritdoc}
     */
    public function syncFromConfig(bool $prune = false): array
    {
        $result = [
            'synced' => [],
            'errors' => [],
        ];

        $guard = $this->guardResolver->guard();
        $map = config('roles.seed.map', []);

        DB::transaction(function () use ($map, $guard, $prune, &$result) {
            foreach ($map as $roleName => $permissionPatterns) {
                try {
                    $role = Role::where('name', $roleName)
                        ->where('guard_name', $guard)
                        ->first();

                    if (!$role) {
                        $result['errors'][] = [
                            'role' => $roleName,
                            'error' => 'Role not found',
                        ];
                        continue;
                    }

                    // Expand wildcards
                    $expandedPermissions = $this->expandWildcards($permissionPatterns, $guard);

                    // Get permission models
                    $permissions = Permission::whereIn('name', $expandedPermissions)
                        ->where('guard_name', $guard)
                        ->get();

                    // Sync permissions
                    $role->syncPermissions($permissions);

                    $result['synced'][] = [
                        'role' => $roleName,
                        'permissions_count' => $permissions->count(),
                        'permissions' => $permissions->pluck('name')->toArray(),
                    ];
                } catch (\Throwable $e) {
                    $result['errors'][] = [
                        'role' => $roleName,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        $this->invalidateCaches();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function matchesPattern(string $pattern, string $permissionName): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if (str_ends_with($pattern, '.*')) {
            $group = substr($pattern, 0, -2);
            return str_starts_with($permissionName, $group . '.');
        }

        return $pattern === $permissionName;
    }

    /**
     * Invalidate relevant caches.
     *
     * @return void
     */
    protected function invalidateCaches(): void
    {
        $this->cacheKeyBuilder->flush();
    }
}

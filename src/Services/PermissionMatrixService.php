<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Services;

use Enadstack\LaravelRoles\Contracts\PermissionMatrixServiceContract;
use Enadstack\LaravelRoles\Contracts\TenantContextContract;
use Enadstack\LaravelRoles\Contracts\GuardResolverContract;
use Enadstack\LaravelRoles\Contracts\CacheKeyBuilderContract;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Support\Facades\Cache;

/**
 * PermissionMatrixService
 *
 * Builds the Role × Permission matrix with zero N+1 queries.
 * Uses maximum 3 queries and contextual caching.
 *
 * @package Enadstack\LaravelRoles\Services
 */
class PermissionMatrixService implements PermissionMatrixServiceContract
{
    /**
     * Cache key for the matrix.
     */
    protected const CACHE_KEY = 'permission_matrix';

    /**
     * Cache key for grouped matrix.
     */
    protected const CACHE_KEY_GROUPED = 'permission_matrix_grouped';

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
     *
     * Uses exactly 2 queries:
     * 1. Fetch all roles with their permissions (eager loaded)
     * 2. Fetch all permissions
     *
     * Then builds the matrix in memory.
     */
    public function build(): array
    {
        return $this->cacheKeyBuilder->remember(self::CACHE_KEY, function () {
            return $this->buildMatrix();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function forGuard(string $guard): array
    {
        $cacheKey = self::CACHE_KEY . ':' . $guard;

        return $this->cacheKeyBuilder->remember($cacheKey, function () use ($guard) {
            return $this->buildMatrixForGuard($guard);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function buildGrouped(): array
    {
        return $this->cacheKeyBuilder->remember(self::CACHE_KEY_GROUPED, function () {
            return $this->buildGroupedMatrix();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function permissionsForRole(int $roleId): array
    {
        $matrix = $this->build();

        // Find the role in the matrix
        foreach ($matrix['matrix'] as $row) {
            foreach ($row['roles'] as $roleName => $roleData) {
                if ($roleData['role_id'] === $roleId) {
                    return [
                        'permission_id' => $row['permission_id'],
                        'permission_name' => $row['permission_name'],
                        'has_permission' => $roleData['has_permission'],
                    ];
                }
            }
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function rolesWithPermission(int $permissionId): array
    {
        $matrix = $this->build();

        foreach ($matrix['matrix'] as $row) {
            if ($row['permission_id'] === $permissionId) {
                $roles = [];
                foreach ($row['roles'] as $roleName => $roleData) {
                    if ($roleData['has_permission']) {
                        $roles[] = [
                            'id' => $roleData['role_id'],
                            'name' => $roleName,
                        ];
                    }
                }
                return $roles;
            }
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function invalidate(): void
    {
        $this->cacheKeyBuilder->forget(self::CACHE_KEY);
        $this->cacheKeyBuilder->forget(self::CACHE_KEY_GROUPED);

        // Also invalidate guard-specific caches
        foreach ($this->guardResolver->availableGuards() as $guard) {
            $this->cacheKeyBuilder->forget(self::CACHE_KEY . ':' . $guard);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cacheStats(): array
    {
        $key = $this->cacheKeyBuilder->key(self::CACHE_KEY);

        return [
            'cache_enabled' => $this->cacheKeyBuilder->isEnabled(),
            'cache_key' => $key,
            'ttl' => $this->cacheKeyBuilder->ttl(),
            'is_cached' => Cache::has($key),
        ];
    }

    /**
     * Build the matrix without caching.
     *
     * @return array
     */
    protected function buildMatrix(): array
    {
        $guard = $this->guardResolver->guard();
        return $this->buildMatrixForGuard($guard);
    }

    /**
     * Build matrix for a specific guard.
     *
     * Query 1: Fetch all roles with permissions (eager loaded)
     * Query 2: Fetch all permissions
     *
     * @param string $guard
     * @return array
     */
    protected function buildMatrixForGuard(string $guard): array
    {
        // Query 1: Get all roles with their permissions (1 main query + 1 relationship query)
        $roles = Role::where('guard_name', $guard)
            ->with('permissions')
            ->get();

        // Query 2: Get all permissions
        $permissions = Permission::where('guard_name', $guard)
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        // Build lookup map for O(1) permission checks
        $rolePermissionsMap = [];
        foreach ($roles as $role) {
            $rolePermissionsMap[$role->id] = $role->permissions
                ->pluck('id')
                ->flip()
                ->toArray();
        }

        // Build the matrix
        $matrix = [];
        foreach ($permissions as $permission) {
            $permissionRow = [
                'permission_id' => $permission->id,
                'permission_name' => $permission->name,
                'permission_label' => $this->resolveLabel($permission),
                'permission_group' => $permission->group ?? null,
                'roles' => [],
            ];

            foreach ($roles as $role) {
                $permissionRow['roles'][$role->name] = [
                    'role_id' => $role->id,
                    'has_permission' => isset($rolePermissionsMap[$role->id][$permission->id]),
                ];
            }

            $matrix[] = $permissionRow;
        }

        return [
            'roles' => $roles->map(fn($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'label' => $this->resolveLabel($r),
            ])->values()->toArray(),
            'permissions' => $permissions->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'label' => $this->resolveLabel($p),
                'group' => $p->group ?? null,
            ])->values()->toArray(),
            'matrix' => $matrix,
        ];
    }

    /**
     * Build grouped matrix.
     *
     * @return array
     */
    protected function buildGroupedMatrix(): array
    {
        $guard = $this->guardResolver->guard();

        // Query 1: Get all roles with their permissions
        $roles = Role::where('guard_name', $guard)
            ->with('permissions')
            ->get();

        // Query 2: Get all permissions grouped
        $permissions = Permission::where('guard_name', $guard)
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        // Build lookup map
        $rolePermissionsMap = [];
        foreach ($roles as $role) {
            $rolePermissionsMap[$role->id] = $role->permissions
                ->pluck('id')
                ->flip()
                ->toArray();
        }

        // Group permissions
        $groups = [];
        foreach ($permissions as $permission) {
            $groupName = $permission->group ?? 'ungrouped';

            if (!isset($groups[$groupName])) {
                $groups[$groupName] = [
                    'label' => $permission->group_label ?? ucfirst($groupName),
                    'permissions' => [],
                ];
            }

            $permissionData = [
                'id' => $permission->id,
                'name' => $permission->name,
                'label' => $this->resolveLabel($permission),
                'roles' => [],
            ];

            foreach ($roles as $role) {
                $permissionData['roles'][$role->name] = [
                    'role_id' => $role->id,
                    'has_permission' => isset($rolePermissionsMap[$role->id][$permission->id]),
                ];
            }

            $groups[$groupName]['permissions'][] = $permissionData;
        }

        return [
            'roles' => $roles->map(fn($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'label' => $this->resolveLabel($r),
            ])->values()->toArray(),
            'groups' => $groups,
            'matrix' => [], // Kept for interface compatibility
        ];
    }

    /**
     * Resolve label for a role or permission.
     *
     * @param Role|Permission $model
     * @return string|null
     */
    protected function resolveLabel(Role|Permission $model): ?string
    {
        $label = $model->label;

        if ($label === null) {
            return null;
        }

        // If i18n is enabled and label is an array
        if (is_array($label)) {
            $locale = app()->getLocale();
            $fallback = config('roles.i18n.fallback', 'en');

            return $label[$locale] ?? $label[$fallback] ?? reset($label) ?? null;
        }

        return $label;
    }
}

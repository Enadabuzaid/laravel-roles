<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Services;

use Enadstack\LaravelRoles\Contracts\PermissionServiceContract;
use Enadstack\LaravelRoles\Contracts\TenantContextContract;
use Enadstack\LaravelRoles\Contracts\GuardResolverContract;
use Enadstack\LaravelRoles\Contracts\CacheKeyBuilderContract;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Events\PermissionCreated;
use Enadstack\LaravelRoles\Events\PermissionUpdated;
use Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PermissionService
 *
 * Core service for permission management operations.
 * All permission access must go through this service, never directly via Spatie.
 *
 * @package Enadstack\LaravelRoles\Services
 */
class PermissionService extends BaseService implements PermissionServiceContract
{
    /**
     * Tenant context instance.
     *
     * @var TenantContextContract|null
     */
    protected ?TenantContextContract $tenantContext = null;

    /**
     * Guard resolver instance.
     *
     * @var GuardResolverContract|null
     */
    protected ?GuardResolverContract $guardResolver = null;

    /**
     * Cache key builder instance.
     *
     * @var CacheKeyBuilderContract|null
     */
    protected ?CacheKeyBuilderContract $cacheKeyBuilder = null;

    /**
     * Create a new service instance.
     *
     * @param TenantContextContract|null $tenantContext
     * @param GuardResolverContract|null $guardResolver
     * @param CacheKeyBuilderContract|null $cacheKeyBuilder
     */
    public function __construct(
        ?TenantContextContract $tenantContext = null,
        ?GuardResolverContract $guardResolver = null,
        ?CacheKeyBuilderContract $cacheKeyBuilder = null
    ) {
        $this->tenantContext = $tenantContext;
        $this->guardResolver = $guardResolver;
        $this->cacheKeyBuilder = $cacheKeyBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $guard = $filters['guard'] ?? $this->getGuard();
        $query = Permission::query()->where('guard_name', $guard);

        // Trash filters
        if (!empty($filters['only_deleted']) || !empty($filters['only_trashed'])) {
            $query->onlyTrashed();
        } elseif (!empty($filters['with_deleted']) || !empty($filters['with_trashed'])) {
            $query->withTrashed();
        }

        // Search filter
        if (!empty($filters['search'])) {
            $query->where(function ($sub) use ($filters) {
                $sub->where('name', 'like', "%{$filters['search']}%");
                if (Schema::hasColumn('permissions', 'description')) {
                    $sub->orWhere('description', 'like', "%{$filters['search']}%");
                }
                if (Schema::hasColumn('permissions', 'label')) {
                    $sub->orWhere('label', 'like', "%{$filters['search']}%");
                }
                if (Schema::hasColumn('permissions', 'group')) {
                    $sub->orWhere('group', 'like', "%{$filters['search']}%");
                }
            });
        }

        // Group filter
        if (!empty($filters['group']) && Schema::hasColumn('permissions', 'group')) {
            $query->where('group', $filters['group']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            if (in_array($filters['status'], RolePermissionStatusEnum::values(), true)) {
                $query->where('status', $filters['status']);
            }
        }

        // Sorting with whitelist validation
        $allowedSorts = ['id', 'name', 'group', 'guard_name', 'status', 'created_at', 'updated_at'];
        $requestedSort = $filters['sort'] ?? 'id';
        $sort = in_array($requestedSort, $allowedSorts, true) ? $requestedSort : 'id';
        $dir = strtolower($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id): ?Permission
    {
        return Permission::find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(string $name, ?string $guardName = null): ?Permission
    {
        $guardName = $guardName ?? $this->getGuard();

        return Permission::where('name', $name)
            ->where('guard_name', $guardName)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Permission
    {
        $data['guard_name'] = $data['guard_name'] ?? $this->getGuard();

        // Apply tenant context if team_scoped
        if ($this->tenantContext && $this->tenantContext->isTeamScoped()) {
            $fk = $this->tenantContext->teamForeignKey();
            if (!isset($data[$fk])) {
                $data[$fk] = $this->tenantContext->tenantId();
            }
        }

        $perm = Permission::create($data);
        $this->flushCaches();

        event(new PermissionCreated($perm));

        return $perm;
    }

    /**
     * {@inheritdoc}
     */
    public function update(Permission $permission, array $data): Permission
    {
        $permission->update($data);
        $this->flushCaches();

        event(new PermissionUpdated($permission));

        return $permission->refresh();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Permission $permission): bool
    {
        $ok = $permission->delete();
        $this->flushCaches();

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    public function forceDelete(Permission $permission): bool
    {
        $ok = $permission->forceDelete();
        $this->flushCaches();

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    public function restore(int $id): bool
    {
        $permission = Permission::withTrashed()->find($id);
        
        if (!$permission || !$permission->trashed()) {
            return false;
        }

        $ok = $permission->restore();
        $this->flushCaches();

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    public function recent(int $limit = 10): Collection
    {
        $query = Permission::query()
            ->latest('created_at')
            ->limit($limit);

        if ($this->guardResolver) {
            $query->where('guard_name', $this->guardResolver->guard());
        }

        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function stats(): array
    {
        $query = Permission::query();

        if ($this->guardResolver) {
            $query->where('guard_name', $this->guardResolver->guard());
        }

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', RolePermissionStatusEnum::ACTIVE->value)->count(),
            'inactive' => (clone $query)->where('status', RolePermissionStatusEnum::INACTIVE->value)->count(),
            'deleted' => (clone $query)->where('status', RolePermissionStatusEnum::DELETED->value)->count(),
            'assigned' => (clone $query)->has('roles')->count(),
            'unassigned' => (clone $query)->doesntHave('roles')->count(),
            'by_group' => $this->getStatsByGroup(),
            'by_status' => $this->getStatsByStatus(),
            'growth' => $this->calculateGrowth(Permission::class, 'created_at'),
        ];
    }

    /**
     * Alias for stats() method - backward compatibility.
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->stats();
    }

    /**
     * Alias for recent() method - backward compatibility.
     *
     * @param int $limit
     * @return Collection
     */
    public function getRecent(int $limit = 10): Collection
    {
        return $this->recent($limit);
    }

    /**
     * Get statistics by group.
     *
     * @return array
     */
    protected function getStatsByGroup(): array
    {
        if (!Schema::hasColumn('permissions', 'group')) {
            return [];
        }

        return Permission::query()
            ->select('group', DB::raw('COUNT(*) as count'))
            ->whereNotNull('group')
            ->groupBy('group')
            ->pluck('count', 'group')
            ->toArray();
    }

    /**
     * Get statistics grouped by status.
     *
     * @return array
     */
    protected function getStatsByStatus(): array
    {
        return Permission::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function changeStatus(Permission $permission, RolePermissionStatusEnum $status): Permission
    {
        $permission->update(['status' => $status->value]);
        $this->flushCaches();

        event(new PermissionUpdated($permission));

        return $permission->refresh();
    }

    /**
     * Activate permission.
     *
     * @param Permission $permission
     * @return Permission
     */
    public function activate(Permission $permission): Permission
    {
        return $this->changeStatus($permission, RolePermissionStatusEnum::ACTIVE);
    }

    /**
     * Deactivate permission.
     *
     * @param Permission $permission
     * @return Permission
     */
    public function deactivate(Permission $permission): Permission
    {
        return $this->changeStatus($permission, RolePermissionStatusEnum::INACTIVE);
    }

    /**
     * Bulk change status.
     *
     * @param array $ids
     * @param RolePermissionStatusEnum $status
     * @return array
     */
    public function bulkChangeStatus(array $ids, RolePermissionStatusEnum $status): array
    {
        $results = ['success' => [], 'failed' => []];

        DB::transaction(function () use ($ids, $status, &$results) {
            $permissions = Permission::whereIn('id', $ids)->get();
            $foundIds = $permissions->pluck('id')->toArray();

            foreach ($ids as $id) {
                if (!in_array($id, $foundIds)) {
                    $results['failed'][] = ['id' => $id, 'reason' => 'Not found'];
                }
            }

            foreach ($permissions as $permission) {
                try {
                    $permission->update(['status' => $status->value]);
                    $results['success'][] = $permission->id;
                } catch (\Exception $e) {
                    $results['failed'][] = ['id' => $permission->id, 'reason' => $e->getMessage()];
                }
            }
        });

        $this->flushCaches();

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupedPermissions(): SupportCollection
    {
        $compute = function () {
            $selectFields = ['name', 'id'];

            if (Schema::hasColumn('permissions', 'label')) {
                $selectFields[] = 'label';
            }
            if (Schema::hasColumn('permissions', 'description')) {
                $selectFields[] = 'description';
            }
            if (Schema::hasColumn('permissions', 'group')) {
                $selectFields[] = 'group';
            }
            if (Schema::hasColumn('permissions', 'group_label')) {
                $selectFields[] = 'group_label';
            }

            $query = Permission::query()
                ->select($selectFields)
                ->where('guard_name', $this->getGuard());

            if (Schema::hasColumn('permissions', 'group')) {
                $query->orderBy('group')->orderBy('name');
            } else {
                $query->orderBy('name');
            }

            return $query->get()
                ->groupBy('group')
                ->map(fn($items) => [
                    'label' => $this->resolveGroupLabel($items->first()),
                    'permissions' => $items->map(fn($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'label' => $this->resolveLabel($p),
                        'description' => $this->resolveDescription($p),
                    ])->values()
                ]);
        };

        if ($this->cacheKeyBuilder && $this->cacheKeyBuilder->isEnabled()) {
            return $this->cacheKeyBuilder->remember('grouped_permissions', $compute);
        }

        return $compute();
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissionMatrix(): array
    {
        $compute = function () {
            $guard = $this->getGuard();

            // Query 1: Get all roles with their permissions (eager loaded)
            $roles = Role::where('guard_name', $guard)->with('permissions')->get();

            // Query 2: Get all permissions
            $permissions = Permission::where('guard_name', $guard)->get();

            // Build lookup map for O(1) permission checks
            $rolePermissionsMap = [];
            foreach ($roles as $role) {
                $rolePermissionsMap[$role->id] = $role->permissions->pluck('id')->flip()->toArray();
            }

            $matrix = [];
            foreach ($permissions as $permission) {
                $permissionRow = [
                    'permission_id' => $permission->id,
                    'permission_name' => $permission->name,
                    'permission_label' => $this->resolveLabel($permission),
                    'permission_group' => $permission->group ?? null,
                    'roles' => []
                ];

                foreach ($roles as $role) {
                    $permissionRow['roles'][$role->name] = [
                        'role_id' => $role->id,
                        'has_permission' => isset($rolePermissionsMap[$role->id][$permission->id])
                    ];
                }

                $matrix[] = $permissionRow;
            }

            return [
                'roles' => $roles->map(fn($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'label' => $r->label ?? null
                ])->values()->toArray(),
                'matrix' => $matrix
            ];
        };

        if ($this->cacheKeyBuilder && $this->cacheKeyBuilder->isEnabled()) {
            return $this->cacheKeyBuilder->remember('permission_matrix', $compute);
        }

        return $compute();
    }

    /**
     * {@inheritdoc}
     */
    public function resolveLabel(Permission $permission, ?string $locale = null): ?string
    {
        $label = $permission->label;

        if ($label === null) {
            return null;
        }

        if (is_array($label)) {
            $locale = $locale ?? app()->getLocale();
            $fallback = config('roles.i18n.fallback', 'en');

            return $label[$locale] ?? $label[$fallback] ?? reset($label) ?? null;
        }

        return $label;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveDescription(Permission $permission, ?string $locale = null): ?string
    {
        $description = $permission->description;

        if ($description === null) {
            return null;
        }

        if (is_array($description)) {
            $locale = $locale ?? app()->getLocale();
            $fallback = config('roles.i18n.fallback', 'en');

            return $description[$locale] ?? $description[$fallback] ?? reset($description) ?? null;
        }

        return $description;
    }

    /**
     * Resolve group label.
     *
     * @param Permission|null $permission
     * @param string|null $locale
     * @return string|null
     */
    protected function resolveGroupLabel(?Permission $permission, ?string $locale = null): ?string
    {
        if (!$permission) {
            return null;
        }

        $groupLabel = $permission->group_label ?? null;

        if ($groupLabel === null) {
            return null;
        }

        if (is_array($groupLabel)) {
            $locale = $locale ?? app()->getLocale();
            $fallback = config('roles.i18n.fallback', 'en');

            return $groupLabel[$locale] ?? $groupLabel[$fallback] ?? reset($groupLabel) ?? null;
        }

        return $groupLabel;
    }

    /**
     * {@inheritdoc}
     */
    public function bulkDelete(array $ids): array
    {
        $results = ['success' => [], 'failed' => []];

        DB::transaction(function () use ($ids, &$results) {
            $perms = Permission::whereIn('id', $ids)->get();
            $found = $perms->pluck('id')->all();

            foreach ($ids as $id) {
                if (!in_array($id, $found, true)) {
                    $results['failed'][] = ['id' => $id, 'reason' => 'Not found'];
                }
            }

            foreach ($perms as $perm) {
                try {
                    $perm->delete();
                    $results['success'][] = $perm->id;
                } catch (\Throwable $e) {
                    $results['failed'][] = ['id' => $perm->id, 'reason' => $e->getMessage()];
                }
            }
        });

        $this->flushCaches();

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function bulkRestore(array $ids): array
    {
        $results = ['success' => [], 'failed' => []];

        DB::transaction(function () use ($ids, &$results) {
            $perms = Permission::withTrashed()->whereIn('id', $ids)->get();
            $found = $perms->pluck('id')->all();

            foreach ($ids as $id) {
                if (!in_array($id, $found, true)) {
                    $results['failed'][] = ['id' => $id, 'reason' => 'Not found'];
                }
            }

            foreach ($perms as $perm) {
                try {
                    if ($perm->trashed()) {
                        $perm->restore();
                        $results['success'][] = $perm->id;
                    } else {
                        $results['failed'][] = ['id' => $perm->id, 'reason' => 'Not deleted'];
                    }
                } catch (\Throwable $e) {
                    $results['failed'][] = ['id' => $perm->id, 'reason' => $e->getMessage()];
                }
            }
        });

        $this->flushCaches();

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function bulkForceDelete(array $ids): array
    {
        $results = ['success' => [], 'failed' => []];

        DB::transaction(function () use ($ids, &$results) {
            $perms = Permission::withTrashed()->whereIn('id', $ids)->get();
            $found = $perms->pluck('id')->all();

            foreach ($ids as $id) {
                if (!in_array($id, $found, true)) {
                    $results['failed'][] = ['id' => $id, 'reason' => 'Not found'];
                }
            }

            foreach ($perms as $perm) {
                try {
                    $perm->forceDelete();
                    $results['success'][] = $perm->id;
                } catch (\Throwable $e) {
                    $results['failed'][] = ['id' => $perm->id, 'reason' => $e->getMessage()];
                }
            }
        });

        $this->flushCaches();

        return $results;
    }

    /**
     * Get the current guard.
     *
     * @return string
     */
    protected function getGuard(): string
    {
        if ($this->guardResolver) {
            return $this->guardResolver->guard();
        }

        return config('roles.guard', config('auth.defaults.guard', 'web'));
    }

    /**
     * Flush caches.
     *
     * @return void
     */
    protected function flushCaches(): void
    {
        if ($this->cacheKeyBuilder) {
            $this->cacheKeyBuilder->flush();
            return;
        }

        // Fallback to old cache flushing logic
        $store = \Illuminate\Support\Facades\Cache::getStore();
        if (method_exists($store, 'tags')) {
            \Illuminate\Support\Facades\Cache::tags(['laravel_roles'])->flush();
        } else {
            \Illuminate\Support\Facades\Cache::forget(config('roles.cache.keys.grouped_permissions', 'laravel_roles.grouped_permissions'));
            \Illuminate\Support\Facades\Cache::forget(config('roles.cache.keys.permission_matrix', 'laravel_roles.permission_matrix'));
        }
    }
}

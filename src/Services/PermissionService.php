<?php

namespace Enadstack\LaravelRoles\Services;

use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Events\PermissionCreated;
use Enadstack\LaravelRoles\Events\PermissionUpdated;
use Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class PermissionService extends BaseService
{
    /**
     * Get paginated list of permissions
     */
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $guard = $filters['guard'] ?? config('roles.guard', config('auth.defaults.guard', 'web'));
        $query = Permission::query()->where('guard_name', $guard);

        // Trash filters
        if (!empty($filters['only_deleted'])) {
            // Show only soft-deleted records (with status 'deleted')
            $query->onlyTrashed();
        } elseif (!empty($filters['with_deleted'])) {
            // Show both active and soft-deleted records
            $query->withTrashed();
        } elseif (!empty($filters['with_trashed'])) {
            // Backward compatibility: with_trashed same as with_deleted
            $query->withTrashed();
        } elseif (!empty($filters['only_trashed'])) {
            // Backward compatibility: only_trashed same as only_deleted
            $query->onlyTrashed();
        }
        // Default: show only non-deleted records

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
     * Get a single permission by ID
     */
    public function find(int $id): ?Permission
    {
        return Permission::find($id);
    }

    /**
     * Create a new permission
     */
    public function create(array $data): Permission
    {
        $data['guard_name'] = $data['guard_name'] ?? config('roles.guard', config('auth.defaults.guard', 'web'));
        $perm = Permission::create($data);
        $this->flushCaches();

        event(new PermissionCreated($perm));

        return $perm;
    }

    /**
     * Update an existing permission
     */
    public function update(Permission $permission, array $data): Permission
    {
        $permission->update($data);
        $this->flushCaches();

        event(new PermissionUpdated($permission));

        return $permission->refresh();
    }

    /**
     * Soft delete a permission
     */
    public function delete(Permission $permission): bool
    {
        $ok = $permission->delete();
        $this->flushCaches();
        return $ok;
    }

    /**
     * Force delete a permission (permanent deletion)
     */
    public function forceDelete(Permission $permission): bool
    {
        $ok = $permission->forceDelete();
        $this->flushCaches();
        return $ok;
    }

    /**
     * Restore a soft-deleted permission
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
     * Get recently created permissions
     */
    public function recent(int $limit = 10): Collection
    {
        return Permission::query()
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get permission statistics with growth data
     */
    public function stats(): array
    {
        return [
            'total' => Permission::count(),
            'active' => Permission::where('status', RolePermissionStatusEnum::ACTIVE->value)->count(),
            'inactive' => Permission::where('status', RolePermissionStatusEnum::INACTIVE->value)->count(),
            'deleted' => Permission::where('status', RolePermissionStatusEnum::DELETED->value)->count(),
            'assigned' => Permission::has('roles')->count(),
            'unassigned' => Permission::doesntHave('roles')->count(),
            'by_group' => $this->getStatsByGroup(),
            'by_status' => $this->getStatsByStatus(),
            'growth' => $this->calculateGrowth(Permission::class, 'created_at'),
        ];
    }

    /**
     * Get statistics by group
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
     * Get statistics grouped by status
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
     * Change permission status
     */
    public function changeStatus(Permission $permission, RolePermissionStatusEnum $status): Permission
    {
        $permission->update(['status' => $status->value]);
        $this->flushCaches();

        event(new PermissionUpdated($permission));

        return $permission->refresh();
    }

    /**
     * Activate permission
     */
    public function activate(Permission $permission): Permission
    {
        return $this->changeStatus($permission, RolePermissionStatusEnum::ACTIVE);
    }

    /**
     * Deactivate permission
     */
    public function deactivate(Permission $permission): Permission
    {
        return $this->changeStatus($permission, RolePermissionStatusEnum::INACTIVE);
    }

    /**
     * Bulk change status
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
     * Get permissions grouped by group (cached)
     */
    public function getGroupedPermissions(): \Illuminate\Support\Collection
    {
        $cacheOn = (bool) config('roles.cache.enabled', true);
        $ttl = (int) config('roles.cache.ttl', 300);
        $key = config('roles.cache.keys.grouped_permissions', 'laravel_roles.grouped_permissions');

        $compute = function () {
            $selectFields = ['name', 'id'];

            // Only select label if i18n is enabled and column exists
            if (Schema::hasColumn('permissions', 'label')) {
                $selectFields[] = 'label';
            }

            if (Schema::hasColumn('permissions', 'group')) {
                $selectFields[] = 'group';
            }
            if (Schema::hasColumn('permissions', 'group_label')) {
                $selectFields[] = 'group_label';
            }

            $query = Permission::query()->select($selectFields);

            if (Schema::hasColumn('permissions', 'group')) {
                $query->orderBy('group')->orderBy('name');
            } else {
                $query->orderBy('name');
            }

            return $query->get()
                ->groupBy('group')
                ->map(fn($items) => [
                    'label' => optional($items->first())->group_label ?? null,
                    'permissions' => $items->map(fn($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'label' => $p->label ?? null
                    ])->values()
                ]);
        };

        if (! $cacheOn) {
            return $compute();
        }

        $store = Cache::getStore();
        if (method_exists($store, 'tags')) {
            return Cache::tags(['laravel_roles'])->remember($key, $ttl, $compute);
        }
        return Cache::remember($key, $ttl, $compute);
    }

    /**
     * Generate permission matrix (roles x permissions) (cached)
     */
    public function getPermissionMatrix(): array
    {
        $cacheOn = (bool) config('roles.cache.enabled', true);
        $ttl = (int) config('roles.cache.ttl', 300);
        $key = config('roles.cache.keys.permission_matrix', 'laravel_roles.permission_matrix');

        $compute = function () {
            $roles = Role::with('permissions')->get();
            $permissions = Permission::all();

            // Create a lookup map for faster permission checks (O(1) instead of O(n))
            $rolePermissionsMap = [];
            foreach ($roles as $role) {
                $rolePermissionsMap[$role->id] = $role->permissions->pluck('id')->flip()->toArray();
            }

            $matrix = [];

            foreach ($permissions as $permission) {
                $permissionRow = [
                    'permission_id' => $permission->id,
                    'permission_name' => $permission->name,
                    'permission_label' => $permission->label ?? null,
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

        if (! $cacheOn) {
            return $compute();
        }

        $store = Cache::getStore();
        if (method_exists($store, 'tags')) {
            return Cache::tags(['laravel_roles'])->remember($key, $ttl, $compute);
        }
        return Cache::remember($key, $ttl, $compute);
    }

    /**
     * Bulk force delete permissions
     */
    public function bulkForceDelete(array $ids): array
    {
        $results = ['success' => [], 'failed' => []];

        DB::transaction(function () use ($ids, &$results) {
            $perms = Permission::withTrashed()->whereIn('id', $ids)->get();
            $found = $perms->pluck('id')->all();

            foreach ($ids as $id) {
                if (! in_array($id, $found, true)) {
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
     * Bulk delete permissions (soft delete)
     */
    public function bulkDelete(array $ids): array
    {
        $results = ['success' => [], 'failed' => []];

        DB::transaction(function () use ($ids, &$results) {
            $perms = Permission::whereIn('id', $ids)->get();
            $found = $perms->pluck('id')->all();

            foreach ($ids as $id) {
                if (! in_array($id, $found, true)) {
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
     * Bulk restore permissions
     */
    public function bulkRestore(array $ids): array
    {
        $results = ['success' => [], 'failed' => []];

        DB::transaction(function () use ($ids, &$results) {
            $perms = Permission::withTrashed()->whereIn('id', $ids)->get();
            $found = $perms->pluck('id')->all();

            foreach ($ids as $id) {
                if (! in_array($id, $found, true)) {
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
     * Flush caches
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


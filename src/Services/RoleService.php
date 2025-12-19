<?php

namespace Enadstack\LaravelRoles\Services;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Events\RoleCreated;
use Enadstack\LaravelRoles\Events\RoleUpdated;
use Enadstack\LaravelRoles\Events\RoleDeleted;
use Enadstack\LaravelRoles\Events\PermissionsAssignedToRole;
use Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RoleService extends BaseService
{
    /**
     * Get paginated list of roles
     */
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Role::query();
        
        // Apply filters
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['guard'])) {
            $query->where('guard_name', $filters['guard']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            if (in_array($filters['status'], RolePermissionStatusEnum::values(), true)) {
                $query->where('status', $filters['status']);
            }
        }

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

        // Sorting with whitelist validation
        $allowedSorts = ['id', 'name', 'guard_name', 'status', 'created_at', 'updated_at'];
        $requestedSort = $filters['sort'] ?? 'id';
        $sort = in_array($requestedSort, $allowedSorts, true) ? $requestedSort : 'id';
        $dir = strtolower($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    /**
     * Get a single role by ID
     */
    public function find(int $id): ?Role
    {
        return Role::find($id);
    }

    /**
     * Create a new role
     */
    public function create(array $data): Role
    {
        $data['guard_name'] = $data['guard_name'] ?? config('roles.guard', config('auth.defaults.guard', 'web'));
        $role = Role::create($data);
        $this->flushCaches();

        event(new RoleCreated($role));

        return $role;
    }

    /**
     * Update an existing role
     */
    public function update(Role $role, array $data): Role
    {
        $role->update($data);
        $this->flushCaches();

        event(new RoleUpdated($role));

        return $role->refresh();
    }

    /**
     * Soft delete a role
     */
    public function delete(Role $role): bool
    {
        $ok = $role->delete();
        $this->flushCaches();

        event(new RoleDeleted($role, false));

        return $ok;
    }

    /**
     * Force delete a role (permanent deletion)
     */
    public function forceDelete(Role $role): bool
    {
        $ok = $role->forceDelete();
        $this->flushCaches();

        event(new RoleDeleted($role, true));

        return $ok;
    }

    /**
     * Restore a soft-deleted role
     */
    public function restore(int $id): bool
    {
        $role = Role::withTrashed()->find($id);
        
        if (!$role || !$role->trashed()) {
            return false;
        }

        $ok = $role->restore();
        $this->flushCaches();
        return $ok;
    }

    /**
     * Bulk delete roles (soft delete)
     */
    public function bulkDelete(array $ids): array
    {
        $results = ['success' => [], 'failed' => []];

        DB::transaction(function () use ($ids, &$results) {
            $roles = Role::whereIn('id', $ids)->get();
            $foundIds = $roles->pluck('id')->toArray();
            
            foreach ($ids as $id) {
                if (!in_array($id, $foundIds)) {
                    $results['failed'][] = ['id' => $id, 'reason' => 'Not found'];
                }
            }
            
            foreach ($roles as $role) {
                try {
                    $role->delete();
                    $results['success'][] = $role->id;
                } catch (\Exception $e) {
                    $results['failed'][] = ['id' => $role->id, 'reason' => $e->getMessage()];
                }
            }
        });

        $this->flushCaches();
        return $results;
    }

    /**
     * Bulk restore roles
     */
    public function bulkRestore(array $ids): array
    {
        $results = ['success' => [], 'failed' => []];

        DB::transaction(function () use ($ids, &$results) {
            $roles = Role::withTrashed()->whereIn('id', $ids)->get();
            $foundIds = $roles->pluck('id')->toArray();
            
            foreach ($ids as $id) {
                if (!in_array($id, $foundIds)) {
                    $results['failed'][] = ['id' => $id, 'reason' => 'Not found'];
                }
            }
            
            foreach ($roles as $role) {
                try {
                    if ($role->trashed()) {
                        $role->restore();
                        $results['success'][] = $role->id;
                    } else {
                        $results['failed'][] = ['id' => $role->id, 'reason' => 'Not deleted'];
                    }
                } catch (\Exception $e) {
                    $results['failed'][] = ['id' => $role->id, 'reason' => $e->getMessage()];
                }
            }
        });

        $this->flushCaches();
        return $results;
    }

    /**
     * Bulk force delete roles
     */
    public function bulkForceDelete(array $ids): array
    {
        $results = ['success' => [], 'failed' => []];

        DB::transaction(function () use ($ids, &$results) {
            $roles = Role::withTrashed()->whereIn('id', $ids)->get();
            $foundIds = $roles->pluck('id')->toArray();

            foreach ($ids as $id) {
                if (!in_array($id, $foundIds)) {
                    $results['failed'][] = ['id' => $id, 'reason' => 'Not found'];
                }
            }

            foreach ($roles as $role) {
                try {
                    $role->forceDelete();
                    $results['success'][] = $role->id;
                } catch (\Exception $e) {
                    $results['failed'][] = ['id' => $role->id, 'reason' => $e->getMessage()];
                }
            }
        });

        $this->flushCaches();
        return $results;
    }

    /**
     * Get recently created roles
     */
    public function recent(int $limit = 10): EloquentCollection
    {
        return Role::query()
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get role statistics with growth data
     */
    public function stats(): array
    {
        return [
            'total' => Role::count(),
            'active' => Role::where('status', RolePermissionStatusEnum::ACTIVE->value)->count(),
            'inactive' => Role::where('status', RolePermissionStatusEnum::INACTIVE->value)->count(),
            'deleted' => Role::where('status', RolePermissionStatusEnum::DELETED->value)->count(),
            'with_permissions' => Role::has('permissions')->count(),
            'without_permissions' => Role::doesntHave('permissions')->count(),
            'by_status' => $this->getStatsByStatus(),
            'growth' => $this->calculateGrowth(Role::class, 'created_at'),
        ];
    }

    /**
     * Get statistics grouped by status
     */
    protected function getStatsByStatus(): array
    {
        return Role::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Change role status
     */
    public function changeStatus(Role $role, RolePermissionStatusEnum $status): Role
    {
        $role->update(['status' => $status->value]);
        $this->flushCaches();

        event(new RoleUpdated($role));

        return $role->refresh();
    }

    /**
     * Activate role
     */
    public function activate(Role $role): Role
    {
        return $this->changeStatus($role, RolePermissionStatusEnum::ACTIVE);
    }

    /**
     * Deactivate role
     */
    public function deactivate(Role $role): Role
    {
        return $this->changeStatus($role, RolePermissionStatusEnum::INACTIVE);
    }

    /**
     * Bulk change status
     */
    public function bulkChangeStatus(array $ids, RolePermissionStatusEnum $status): array
    {
        $results = ['success' => [], 'failed' => []];

        DB::transaction(function () use ($ids, $status, &$results) {
            $roles = Role::whereIn('id', $ids)->get();
            $foundIds = $roles->pluck('id')->toArray();

            foreach ($ids as $id) {
                if (!in_array($id, $foundIds)) {
                    $results['failed'][] = ['id' => $id, 'reason' => 'Not found'];
                }
            }

            foreach ($roles as $role) {
                try {
                    $role->update(['status' => $status->value]);
                    $results['success'][] = $role->id;
                } catch (\Exception $e) {
                    $results['failed'][] = ['id' => $role->id, 'reason' => $e->getMessage()];
                }
            }
        });

        $this->flushCaches();
        return $results;
    }

    /**
     * Assign permissions to a role
     */
    public function assignPermissions(Role $role, array $permissionIds): Role
    {
        $permissions = Permission::whereIn('id', $permissionIds)->get();
        $role->syncPermissions($permissions);
        $this->flushCaches();

        event(new PermissionsAssignedToRole($role, $permissionIds));

        return $role->refresh();
    }

    /**
     * Attach a single permission to role (idempotent)
     */
    public function addPermission(Role $role, int|Permission $permission): Role
    {
        $perm = is_int($permission)
            ? Permission::findOrFail($permission)
            : $permission;

        $role->givePermissionTo($perm);
        $this->flushCaches();
        return $role->refresh()->load('permissions');
    }

    /**
     * Detach a single permission from role (idempotent)
     */
    public function removePermission(Role $role, int|Permission $permission): Role
    {
        $perm = is_int($permission)
            ? Permission::findOrFail($permission)
            : $permission;

        $role->revokePermissionTo($perm);
        $this->flushCaches();
        return $role->refresh()->load('permissions');
    }

    /**
     * Clone a role with its permissions
     *
     * @param Role   $role       Source role to clone
     * @param string $name       New role name
     * @param array  $attributes Optional additional attributes (e.g., label, description, guard_name)
     */
    public function cloneWithPermissions(Role $role, string $name, array $attributes = []): Role
    {
        $data = array_merge([
            'name' => $name,
            'guard_name' => $attributes['guard_name'] ?? $role->guard_name,
        ], $attributes);

        // Remove unsafe keys
        unset($data['id']);

        return DB::transaction(function () use ($role, $data) {
            $new = Role::create($data);
            $new->syncPermissions($role->permissions()->pluck('id')->all());
            $this->flushCaches();
            return $new->load('permissions');
        });
    }

    /**
     * Get role with its permissions
     */
    public function getRoleWithPermissions(int $id): ?Role
    {
        return Role::with('permissions')->find($id);
    }

    /**
     * Get all permissions grouped by role
     */
    public function getPermissionsGroupedByRole(): SupportCollection
    {
        return Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label ?? null,
                'permissions' => $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'label' => $permission->label ?? null,
                        'group' => $permission->group ?? null,
                    ];
                }),
            ];
        });
    }

    /**
     * Flush package caches
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

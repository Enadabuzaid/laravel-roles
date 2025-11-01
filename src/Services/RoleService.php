<?php

namespace Enadstack\LaravelRoles\Services;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RoleService
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

        // Sorting with whitelist validation
        $allowedSorts = ['id', 'name', 'guard_name', 'created_at', 'updated_at'];
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
        return Role::create($data);
    }

    /**
     * Update an existing role
     */
    public function update(Role $role, array $data): Role
    {
        $role->update($data);
        return $role->refresh();
    }

    /**
     * Soft delete a role
     */
    public function delete(Role $role): bool
    {
        return $role->delete();
    }

    /**
     * Force delete a role (permanent deletion)
     */
    public function forceDelete(Role $role): bool
    {
        return $role->forceDelete();
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

        return $role->restore();
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

        return $results;
    }

    /**
     * Get recently created roles
     */
    public function recent(int $limit = 10): Collection
    {
        return Role::query()
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get role statistics
     */
    public function stats(): array
    {
        return [
            'total' => Role::count(),
            'active' => Role::whereNull('deleted_at')->count(),
            'deleted' => Role::onlyTrashed()->count(),
            'with_permissions' => Role::has('permissions')->count(),
            'without_permissions' => Role::doesntHave('permissions')->count(),
        ];
    }

    /**
     * Assign permissions to a role
     */
    public function assignPermissions(Role $role, array $permissionIds): Role
    {
        $permissions = Permission::whereIn('id', $permissionIds)->get();
        $role->syncPermissions($permissions);
        return $role->refresh();
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
    public function getPermissionsGroupedByRole(): Collection
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
}

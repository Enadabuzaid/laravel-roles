<?php

namespace Enadstack\LaravelRoles\Services;

use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PermissionService
{
    /**
     * Get paginated list of permissions
     */
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $guard = $filters['guard'] ?? config('roles.guard', config('auth.defaults.guard', 'web'));
        $query = Permission::query()->where('guard_name', $guard);

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

        // Sorting with whitelist validation
        $allowedSorts = ['id', 'name', 'group', 'guard_name', 'created_at', 'updated_at'];
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
        return Permission::create($data);
    }

    /**
     * Update an existing permission
     */
    public function update(Permission $permission, array $data): Permission
    {
        $permission->update($data);
        return $permission->refresh();
    }

    /**
     * Soft delete a permission
     */
    public function delete(Permission $permission): bool
    {
        return $permission->delete();
    }

    /**
     * Force delete a permission (permanent deletion)
     */
    public function forceDelete(Permission $permission): bool
    {
        return $permission->forceDelete();
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

        return $permission->restore();
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
     * Get permission statistics
     */
    public function stats(): array
    {
        return [
            'total' => Permission::count(),
            'active' => Permission::whereNull('deleted_at')->count(),
            'deleted' => Permission::onlyTrashed()->count(),
            'assigned' => Permission::has('roles')->count(),
            'unassigned' => Permission::doesntHave('roles')->count(),
            'by_group' => $this->getStatsByGroup(),
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
     * Get permissions grouped by group
     */
    public function getGroupedPermissions(): Collection
    {
        $selectFields = ['name', 'label', 'id'];
        
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
                'label' => optional($items->first())->group_label,
                'permissions' => $items->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name, 
                    'label' => $p->label
                ])->values()
            ]);
    }

    /**
     * Generate permission matrix (roles x permissions)
     */
    public function getPermissionMatrix(): array
    {
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
    }
}

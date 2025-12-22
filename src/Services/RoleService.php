<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Services;

use Enadstack\LaravelRoles\Contracts\RoleServiceContract;
use Enadstack\LaravelRoles\Contracts\TenantContextContract;
use Enadstack\LaravelRoles\Contracts\GuardResolverContract;
use Enadstack\LaravelRoles\Contracts\CacheKeyBuilderContract;
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

/**
 * RoleService
 *
 * Core service for role management operations.
 * All role access must go through this service, never directly via Spatie.
 *
 * @package Enadstack\LaravelRoles\Services
 */
class RoleService extends BaseService implements RoleServiceContract
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
     * Get paginated list of roles.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Role::query();
        
        // Apply guard filter from resolver if available
        if ($this->guardResolver && empty($filters['guard'])) {
            $query->where('guard_name', $this->guardResolver->guard());
        }

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
        if (!empty($filters['only_deleted']) || !empty($filters['only_trashed'])) {
            $query->onlyTrashed();
        } elseif (!empty($filters['with_deleted']) || !empty($filters['with_trashed'])) {
            $query->withTrashed();
        }

        // Sorting with whitelist validation
        $allowedSorts = ['id', 'name', 'guard_name', 'status', 'created_at', 'updated_at'];
        $requestedSort = $filters['sort'] ?? 'id';
        $sort = in_array($requestedSort, $allowedSorts, true) ? $requestedSort : 'id';
        $dir = strtolower($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id): ?Role
    {
        return Role::find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(string $name, ?string $guardName = null): ?Role
    {
        $guardName = $guardName ?? $this->getGuard();

        return Role::where('name', $name)
            ->where('guard_name', $guardName)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Role
    {
        $data['guard_name'] = $data['guard_name'] ?? $this->getGuard();

        // Apply tenant context if team_scoped
        if ($this->tenantContext && $this->tenantContext->isTeamScoped()) {
            $fk = $this->tenantContext->teamForeignKey();
            if (!isset($data[$fk])) {
                $data[$fk] = $this->tenantContext->tenantId();
            }
        }

        $role = Role::create($data);
        $this->flushCaches();

        event(new RoleCreated($role));

        return $role;
    }

    /**
     * {@inheritdoc}
     */
    public function update(Role $role, array $data): Role
    {
        $role->update($data);
        $this->flushCaches();

        event(new RoleUpdated($role));

        return $role->refresh();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Role $role): bool
    {
        $ok = $role->delete();
        $this->flushCaches();

        event(new RoleDeleted($role, false));

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    public function forceDelete(Role $role): bool
    {
        $ok = $role->forceDelete();
        $this->flushCaches();

        event(new RoleDeleted($role, true));

        return $ok;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function recent(int $limit = 10): EloquentCollection
    {
        $query = Role::query()->latest('created_at')->limit($limit);

        if ($this->guardResolver) {
            $query->where('guard_name', $this->guardResolver->guard());
        }

        return $query->get();
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
     * @return EloquentCollection
     */
    public function getRecent(int $limit = 10): EloquentCollection
    {
        return $this->recent($limit);
    }

    /**
     * {@inheritdoc}
     */
    public function stats(): array
    {
        $query = Role::query();

        if ($this->guardResolver) {
            $query->where('guard_name', $this->guardResolver->guard());
        }

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', RolePermissionStatusEnum::ACTIVE->value)->count(),
            'inactive' => (clone $query)->where('status', RolePermissionStatusEnum::INACTIVE->value)->count(),
            'deleted' => (clone $query)->where('status', RolePermissionStatusEnum::DELETED->value)->count(),
            'with_permissions' => (clone $query)->has('permissions')->count(),
            'without_permissions' => (clone $query)->doesntHave('permissions')->count(),
            'by_status' => $this->getStatsByStatus(),
            'growth' => $this->calculateGrowth(Role::class, 'created_at'),
        ];
    }

    /**
     * Get statistics grouped by status.
     *
     * @return array
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
     * {@inheritdoc}
     */
    public function changeStatus(Role $role, RolePermissionStatusEnum $status): Role
    {
        $role->update(['status' => $status->value]);
        $this->flushCaches();

        event(new RoleUpdated($role));

        return $role->refresh();
    }

    /**
     * Activate role.
     *
     * @param Role $role
     * @return Role
     */
    public function activate(Role $role): Role
    {
        return $this->changeStatus($role, RolePermissionStatusEnum::ACTIVE);
    }

    /**
     * Deactivate role.
     *
     * @param Role $role
     * @return Role
     */
    public function deactivate(Role $role): Role
    {
        return $this->changeStatus($role, RolePermissionStatusEnum::INACTIVE);
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
     * Assign permissions to a role.
     *
     * @param Role $role
     * @param array $permissionIds
     * @return Role
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
     * Attach a single permission to role (idempotent).
     *
     * @param Role $role
     * @param int|Permission $permission
     * @return Role
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
     * Detach a single permission from role (idempotent).
     *
     * @param Role $role
     * @param int|Permission $permission
     * @return Role
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getRoleWithPermissions(int $id): ?Role
    {
        return Role::with('permissions')->find($id);
    }

    /**
     * {@inheritdoc}
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
     * Flush package caches.
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

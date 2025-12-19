# Backend Implementation Guide - Laravel Roles v2.0

## 🏗️ BACKEND ARCHITECTURE DETAILS

### 1. TENANCY ADAPTER PATTERN

#### Interface Contract
**File:** `src/Contracts/TenancyAdapterInterface.php`

```php
<?php

namespace Enadstack\LaravelRoles\Contracts;

interface TenancyAdapterInterface
{
    /**
     * Get the current tenant ID
     */
    public function getCurrentTenantId(): ?int;
    
    /**
     * Set the tenant context
     */
    public function setTenantId(?int $tenantId): void;
    
    /**
     * Check if multi-tenancy is active
     */
    public function isActive(): bool;
    
    /**
     * Get the tenant foreign key column name
     */
    public function getTenantColumn(): string;
    
    /**
     * Apply tenant scope to query builder
     */
    public function applyScope($query): void;
}
```

#### Stancl Tenancy Adapter
**File:** `src/Support/TenancyAdapters/StanclTenancyAdapter.php`

```php
<?php

namespace Enadstack\LaravelRoles\Support\TenancyAdapters;

use Enadstack\LaravelRoles\Contracts\TenancyAdapterInterface;
use Stancl\Tenancy\Tenancy;

class StanclTenancyAdapter implements TenancyAdapterInterface
{
    public function __construct(
        protected ?Tenancy $tenancy = null,
        protected string $tenantColumn = 'tenant_id'
    ) {
        $this->tenancy = $tenancy ?? app(Tenancy::class);
    }
    
    public function getCurrentTenantId(): ?int
    {
        return $this->tenancy->tenant?->getTenantKey();
    }
    
    public function setTenantId(?int $tenantId): void
    {
        if ($tenantId) {
            $this->tenancy->initialize($tenantId);
        }
    }
    
    public function isActive(): bool
    {
        return $this->tenancy->initialized;
    }
    
    public function getTenantColumn(): string
    {
        return $this->tenantColumn;
    }
    
    public function applyScope($query): void
    {
        if ($tenantId = $this->getCurrentTenantId()) {
            $query->where($this->getTenantColumn(), $tenantId);
        }
    }
}
```

#### Spatie Multitenancy Adapter
**File:** `src/Support/TenancyAdapters/SpatieTenancyAdapter.php`

```php
<?php

namespace Enadstack\LaravelRoles\Support\TenancyAdapters;

use Enadstack\LaravelRoles\Contracts\TenancyAdapterInterface;
use Spatie\Multitenancy\Models\Tenant;

class SpatieTenancyAdapter implements TenancyAdapterInterface
{
    public function __construct(
        protected string $tenantColumn = 'tenant_id'
    ) {}
    
    public function getCurrentTenantId(): ?int
    {
        return Tenant::current()?->id;
    }
    
    public function setTenantId(?int $tenantId): void
    {
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            $tenant?->makeCurrent();
        }
    }
    
    public function isActive(): bool
    {
        return Tenant::checkCurrent();
    }
    
    public function getTenantColumn(): string
    {
        return $this->tenantColumn;
    }
    
    public function applyScope($query): void
    {
        if ($tenantId = $this->getCurrentTenantId()) {
            $query->where($this->getTenantColumn(), $tenantId);
        }
    }
}
```

#### Null Adapter (Single Tenant)
**File:** `src/Support/TenancyAdapters/NullTenancyAdapter.php`

```php
<?php

namespace Enadstack\LaravelRoles\Support\TenancyAdapters;

use Enadstack\LaravelRoles\Contracts\TenancyAdapterInterface;

class NullTenancyAdapter implements TenancyAdapterInterface
{
    public function getCurrentTenantId(): ?int
    {
        return null;
    }
    
    public function setTenantId(?int $tenantId): void
    {
        // No-op for single tenant
    }
    
    public function isActive(): bool
    {
        return false;
    }
    
    public function getTenantColumn(): string
    {
        return 'team_id';
    }
    
    public function applyScope($query): void
    {
        // No-op for single tenant
    }
}
```

---

### 2. TENANCY SERVICE

**File:** `src/Services/TenancyService.php`

```php
<?php

namespace Enadstack\LaravelRoles\Services;

use Enadstack\LaravelRoles\Contracts\TenancyAdapterInterface;
use Enadstack\LaravelRoles\Support\TenancyAdapters\{
    StanclTenancyAdapter,
    SpatieTenancyAdapter,
    NullTenancyAdapter
};
use Enadstack\LaravelRoles\Exceptions\InvalidTenancyModeException;

class TenancyService
{
    protected TenancyAdapterInterface $adapter;
    
    public function __construct()
    {
        $this->adapter = $this->resolveAdapter();
    }
    
    protected function resolveAdapter(): TenancyAdapterInterface
    {
        $mode = config('roles.tenancy.mode');
        $provider = config('roles.tenancy.provider');
        $column = config('roles.tenancy.team_foreign_key');
        
        return match($mode) {
            'single' => new NullTenancyAdapter(),
            'team_scoped' => $this->resolveTeamScopedAdapter($provider, $column),
            'multi_database' => $this->resolveMultiDatabaseAdapter($provider, $column),
            default => throw new InvalidTenancyModeException("Invalid tenancy mode: {$mode}")
        };
    }
    
    protected function resolveTeamScopedAdapter(?string $provider, string $column): TenancyAdapterInterface
    {
        // For team_scoped, we use Spatie Permission's built-in teams feature
        return new NullTenancyAdapter(); // Spatie handles this internally
    }
    
    protected function resolveMultiDatabaseAdapter(?string $provider, string $column): TenancyAdapterInterface
    {
        return match($provider) {
            'stancl/tenancy' => new StanclTenancyAdapter(tenantColumn: $column),
            'spatie/laravel-multitenancy' => new SpatieTenancyAdapter(tenantColumn: $column),
            default => throw new InvalidTenancyModeException("Unsupported tenancy provider: {$provider}")
        };
    }
    
    public function getAdapter(): TenancyAdapterInterface
    {
        return $this->adapter;
    }
    
    public function getCurrentTenantId(): ?int
    {
        return $this->adapter->getCurrentTenantId();
    }
    
    public function setTenantId(?int $tenantId): void
    {
        $this->adapter->setTenantId($tenantId);
        
        // Also set Spatie Permission team ID if team_scoped
        if (config('roles.tenancy.mode') === 'team_scoped' && $tenantId) {
            setPermissionsTeamId($tenantId);
        }
    }
    
    public function isActive(): bool
    {
        return $this->adapter->isActive();
    }
}
```

---

### 3. REPOSITORY PATTERN

**File:** `src/Repositories/RoleRepository.php`

```php
<?php

namespace Enadstack\LaravelRoles\Repositories;

use Spatie\Permission\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class RoleRepository
{
    public function __construct(
        protected TenancyService $tenancyService
    ) {}
    
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Role::query();
        
        // Apply tenancy scope
        $this->tenancyService->getAdapter()->applyScope($query);
        
        // Search
        if ($search = $filters['search'] ?? null) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereJsonContains('label', $search);
            });
        }
        
        // With trashed
        if ($filters['with_trashed'] ?? false) {
            $query->withTrashed();
        }
        
        // Only trashed
        if ($filters['only_trashed'] ?? false) {
            $query->onlyTrashed();
        }
        
        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        // Eager load
        $query->withCount('permissions', 'users');
        
        return $query->paginate($filters['per_page'] ?? 15);
    }
    
    public function findById(int $id): ?Role
    {
        $query = Role::query();
        $this->tenancyService->getAdapter()->applyScope($query);
        
        return $query->with('permissions')->find($id);
    }
    
    public function create(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? config('roles.guard'),
            'label' => $data['label'] ?? null,
            'description' => $data['description'] ?? null,
        ]);
        
        return $role;
    }
    
    public function update(Role $role, array $data): Role
    {
        $role->update([
            'name' => $data['name'] ?? $role->name,
            'label' => $data['label'] ?? $role->label,
            'description' => $data['description'] ?? $role->description,
        ]);
        
        return $role->fresh();
    }
    
    public function delete(Role $role): bool
    {
        return $role->delete();
    }
    
    public function forceDelete(Role $role): bool
    {
        return $role->forceDelete();
    }
    
    public function restore(Role $role): bool
    {
        return $role->restore();
    }
    
    public function stats(): array
    {
        $query = Role::query();
        $this->tenancyService->getAdapter()->applyScope($query);
        
        return [
            'total' => (clone $query)->withTrashed()->count(),
            'active' => (clone $query)->count(),
            'deleted' => (clone $query)->onlyTrashed()->count(),
            'with_permissions' => (clone $query)->has('permissions')->count(),
            'without_permissions' => (clone $query)->doesntHave('permissions')->count(),
        ];
    }
}
```

---

### 4. ENHANCED ROLE SERVICE

**File:** `src/Services/RoleService.php` (Updated)

```php
<?php

namespace Enadstack\LaravelRoles\Services;

use Enadstack\LaravelRoles\Repositories\RoleRepository;
use Enadstack\LaravelRoles\Events\{RoleCreated, RoleUpdated, RoleDeleted};
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RoleService
{
    public function __construct(
        protected RoleRepository $repository,
        protected CacheService $cacheService,
        protected TenancyService $tenancyService
    ) {}
    
    public function list(array $filters = [])
    {
        return $this->repository->paginate($filters);
    }
    
    public function find(int $id): ?Role
    {
        return $this->repository->findById($id);
    }
    
    public function create(array $data): Role
    {
        return DB::transaction(function() use ($data) {
            $role = $this->repository->create($data);
            
            event(new RoleCreated($role));
            $this->cacheService->clearRolesCache();
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            
            return $role;
        });
    }
    
    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function() use ($role, $data) {
            $updated = $this->repository->update($role, $data);
            
            event(new RoleUpdated($updated));
            $this->cacheService->clearRolesCache();
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            
            return $updated;
        });
    }
    
    public function delete(Role $role): bool
    {
        return DB::transaction(function() use ($role) {
            $result = $this->repository->delete($role);
            
            event(new RoleDeleted($role));
            $this->cacheService->clearRolesCache();
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            
            return $result;
        });
    }
    
    public function assignPermissions(Role $role, array $permissionIds): Role
    {
        return DB::transaction(function() use ($role, $permissionIds) {
            $role->syncPermissions($permissionIds);
            
            event(new PermissionsAssignedToRole($role, $permissionIds));
            $this->cacheService->clearPermissionsCache();
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            
            return $role->fresh(['permissions']);
        });
    }
    
    public function stats(): array
    {
        return $this->repository->stats();
    }
}
```

---

### 5. CACHE SERVICE

**File:** `src/Services/CacheService.php`

```php
<?php

namespace Enadstack\LaravelRoles\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    protected bool $enabled;
    protected int $ttl;
    protected array $tags;
    
    public function __construct()
    {
        $this->enabled = config('roles.cache.enabled', true);
        $this->ttl = config('roles.cache.ttl', 300);
        $this->tags = config('roles.cache.tags', ['roles', 'permissions']);
    }
    
    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        if (!$this->enabled) {
            return $callback();
        }
        
        $ttl = $ttl ?? $this->ttl;
        
        if ($this->supportsTags()) {
            return Cache::tags($this->tags)->remember($key, $ttl, $callback);
        }
        
        return Cache::remember($key, $ttl, $callback);
    }
    
    public function forget(string $key): void
    {
        if (!$this->enabled) {
            return;
        }
        
        if ($this->supportsTags()) {
            Cache::tags($this->tags)->forget($key);
        } else {
            Cache::forget($key);
        }
    }
    
    public function clearRolesCache(): void
    {
        if (!$this->enabled) {
            return;
        }
        
        if ($this->supportsTags()) {
            Cache::tags(['roles'])->flush();
        } else {
            // Clear specific keys
            $keys = config('roles.cache.keys', []);
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
    }
    
    public function clearPermissionsCache(): void
    {
        if (!$this->enabled) {
            return;
        }
        
        if ($this->supportsTags()) {
            Cache::tags(['permissions'])->flush();
        }
    }
    
    public function clearAll(): void
    {
        if (!$this->enabled) {
            return;
        }
        
        if ($this->supportsTags()) {
            Cache::tags($this->tags)->flush();
        }
    }
    
    protected function supportsTags(): bool
    {
        return method_exists(Cache::getStore(), 'tags');
    }
}
```

---

### 6. USER-ROLE CONTROLLER

**File:** `src/Http/Controllers/UserRoleController.php`

```php
<?php

namespace Enadstack\LaravelRoles\Http\Controllers;

use App\Models\User;
use Enadstack\LaravelRoles\Http\Requests\{AssignRolesRequest, SyncRolesRequest};
use Enadstack\LaravelRoles\Http\Resources\{RoleResource, UserRoleResource};
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserRoleController extends Controller
{
    /**
     * Get user's roles
     */
    public function index(User $user): JsonResponse
    {
        $this->authorize('view', $user);
        
        return response()->json([
            'data' => RoleResource::collection($user->roles),
        ]);
    }
    
    /**
     * Assign roles to user (additive)
     */
    public function assign(User $user, AssignRolesRequest $request): JsonResponse
    {
        $this->authorize('update', $user);
        
        DB::transaction(function() use ($user, $request) {
            $user->assignRole($request->role_ids);
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        });
        
        return response()->json([
            'message' => __('roles::messages.roles_assigned'),
            'data' => RoleResource::collection($user->fresh()->roles),
        ]);
    }
    
    /**
     * Sync roles to user (replaces all)
     */
    public function sync(User $user, SyncRolesRequest $request): JsonResponse
    {
        $this->authorize('update', $user);
        
        DB::transaction(function() use ($user, $request) {
            $user->syncRoles($request->role_ids);
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        });
        
        return response()->json([
            'message' => __('roles::messages.roles_synced'),
            'data' => RoleResource::collection($user->fresh()->roles),
        ]);
    }
    
    /**
     * Revoke a role from user
     */
    public function revoke(User $user, int $roleId): JsonResponse
    {
        $this->authorize('update', $user);
        
        DB::transaction(function() use ($user, $roleId) {
            $user->removeRole($roleId);
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        });
        
        return response()->json(null, 204);
    }
}
```

---

### 7. FORM REQUESTS

**File:** `src/Http/Requests/AssignRolesRequest.php`

```php
<?php

namespace Enadstack\LaravelRoles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }
    
    public function rules(): array
    {
        return [
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', 'exists:roles,id'],
        ];
    }
    
    public function messages(): array
    {
        return [
            'role_ids.required' => __('roles::validation.role_ids_required'),
            'role_ids.*.exists' => __('roles::validation.role_not_found'),
        ];
    }
}
```

**File:** `src/Http/Requests/SyncMatrixRequest.php`

```php
<?php

namespace Enadstack\LaravelRoles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('permissions.update');
    }
    
    public function rules(): array
    {
        return [
            'matrix' => ['required', 'array'],
            'matrix.*.role_id' => ['required', 'integer', 'exists:roles,id'],
            'matrix.*.permission_ids' => ['required', 'array'],
            'matrix.*.permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
```

---

### 8. MIDDLEWARE

**File:** `src/Http/Middleware/LocaleMiddleware.php`

```php
<?php

namespace Enadstack\LaravelRoles\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LocaleMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('roles.i18n.enabled')) {
            return $next($request);
        }
        
        $locale = $request->header('Accept-Language')
            ?? $request->get('locale')
            ?? config('roles.i18n.default');
        
        if (in_array($locale, config('roles.i18n.locales'))) {
            app()->setLocale($locale);
        }
        
        return $next($request);
    }
}
```

---

## 🧪 TESTING EXAMPLES

### Feature Test: User-Role Assignment

**File:** `tests/Feature/UserRoleAssignmentTest.php`

```php
<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\{postJson, deleteJson, actingAs};

beforeEach(function() {
    $this->user = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('users.update');
    
    $this->role = Role::create(['name' => 'editor']);
});

it('assigns roles to user', function() {
    actingAs($this->admin);
    
    $response = postJson("/api/admin/acl/users/{$this->user->id}/roles/assign", [
        'role_ids' => [$this->role->id],
    ]);
    
    $response->assertStatus(200)
        ->assertJsonStructure(['message', 'data']);
    
    expect($this->user->fresh()->hasRole('editor'))->toBeTrue();
});

it('syncs roles to user', function() {
    $role2 = Role::create(['name' => 'manager']);
    $this->user->assignRole($this->role);
    
    actingAs($this->admin);
    
    $response = postJson("/api/admin/acl/users/{$this->user->id}/roles/sync", [
        'role_ids' => [$role2->id],
    ]);
    
    $response->assertStatus(200);
    
    $user = $this->user->fresh();
    expect($user->hasRole('manager'))->toBeTrue();
    expect($user->hasRole('editor'))->toBeFalse();
});

it('revokes role from user', function() {
    $this->user->assignRole($this->role);
    
    actingAs($this->admin);
    
    $response = deleteJson("/api/admin/acl/users/{$this->user->id}/roles/{$this->role->id}");
    
    $response->assertStatus(204);
    
    expect($this->user->fresh()->hasRole('editor'))->toBeFalse();
});

it('clears Spatie cache after role assignment', function() {
    actingAs($this->admin);
    
    postJson("/api/admin/acl/users/{$this->user->id}/roles/assign", [
        'role_ids' => [$this->role->id],
    ]);
    
    // Verify cache was cleared by checking fresh permissions
    expect($this->user->fresh()->hasRole('editor'))->toBeTrue();
});
```

---

## 📝 VALIDATION RULES REFERENCE

### Role Validation
```php
'name' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/', 'unique:roles,name'],
'label' => ['nullable', 'array'],
'label.*' => ['string', 'max:100'],
'description' => ['nullable', 'array'],
'description.*' => ['string', 'max:500'],
'guard_name' => ['required', 'string', 'in:web,api,sanctum'],
```

### Permission Validation
```php
'name' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9.-]+$/', 'unique:permissions,name'],
'group' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/'],
'label' => ['nullable', 'array'],
'description' => ['nullable', 'array'],
```

---

**Implementation Guide Version:** 1.0  
**Last Updated:** 2025-12-19

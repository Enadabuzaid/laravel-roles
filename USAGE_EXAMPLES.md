# Usage Examples

Practical examples of using the Laravel Roles & Permissions package in your application.

## Table of Contents

1. [Basic Setup](#basic-setup)
2. [Working with Roles](#working-with-roles)
3. [Working with Permissions](#working-with-permissions)
4. [Permission Assignment](#permission-assignment)
5. [Bulk Operations](#bulk-operations)
6. [Statistics and Analytics](#statistics-and-analytics)
7. [Multi-tenancy](#multi-tenancy)
8. [Frontend Integration](#frontend-integration)

---

## Basic Setup

### Installation

```bash
composer require enadstack/laravel-roles
php artisan roles:install
```

### Configuration

```php
// config/roles.php
return [
    'i18n' => [
        'enabled' => true,
        'locales' => ['en', 'ar'],
        'default' => 'en',
    ],
    'guard' => 'web',
    'routes' => [
        'prefix' => 'admin/acl',
        'middleware' => ['api', 'auth:sanctum'],
    ],
];
```

---

## Working with Roles

### Using the Service Layer

```php
use Enadstack\LaravelRoles\Services\RoleService;

class RoleManagementController extends Controller
{
    public function __construct(
        protected RoleService $roleService
    ) {}

    // List all roles with filtering
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->get('search'),
            'guard' => $request->get('guard', 'web'),
            'sort' => $request->get('sort', 'created_at'),
            'direction' => $request->get('direction', 'desc'),
        ];
        
        return $this->roleService->list($filters, 20);
    }

    // Create a new role
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'label' => 'nullable|array',
            'label.en' => 'required_with:label',
            'description' => 'nullable|array',
        ]);

        $role = $this->roleService->create($validated);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role
        ], 201);
    }

    // Update existing role
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:roles,name,' . $role->id,
            'label' => 'nullable|array',
            'description' => 'nullable|array',
        ]);

        $role = $this->roleService->update($role, $validated);

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role
        ]);
    }

    // Soft delete a role
    public function destroy(Role $role)
    {
        $this->roleService->delete($role);

        return response()->json([
            'message' => 'Role deleted successfully'
        ]);
    }

    // Restore a deleted role
    public function restore(int $id)
    {
        $restored = $this->roleService->restore($id);

        if (!$restored) {
            return response()->json([
                'message' => 'Role not found or not deleted'
            ], 404);
        }

        return response()->json([
            'message' => 'Role restored successfully'
        ]);
    }

    // Force delete (permanent)
    public function forceDelete(Role $role)
    {
        $this->roleService->forceDelete($role);

        return response()->json([
            'message' => 'Role permanently deleted'
        ]);
    }

    // Get recent roles
    public function recent()
    {
        $recentRoles = $this->roleService->recent(10);

        return response()->json($recentRoles);
    }

    // Get role statistics
    public function stats()
    {
        $stats = $this->roleService->stats();

        return response()->json($stats);
    }
}
```

---

## Working with Permissions

### Using the Permission Service

```php
use Enadstack\LaravelRoles\Services\PermissionService;

class PermissionManagementController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    // List permissions with grouping
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->get('search'),
            'group' => $request->get('group'),
            'guard' => $request->get('guard', 'web'),
            'sort' => $request->get('sort', 'name'),
            'direction' => $request->get('direction', 'asc'),
        ];
        
        return $this->permissionService->list($filters, 50);
    }

    // Create permission with group
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
            'group' => 'required|string|max:255',
            'label' => 'nullable|array',
            'description' => 'nullable|array',
            'group_label' => 'nullable|array',
        ]);

        $permission = $this->permissionService->create($validated);

        return response()->json([
            'message' => 'Permission created successfully',
            'permission' => $permission
        ], 201);
    }

    // Get permissions grouped by category
    public function groups()
    {
        $grouped = $this->permissionService->getGroupedPermissions();

        return response()->json($grouped);
    }

    // Get permission matrix (roles × permissions)
    public function matrix()
    {
        $matrix = $this->permissionService->getPermissionMatrix();

        return response()->json($matrix);
    }

    // Get permission statistics
    public function stats()
    {
        $stats = $this->permissionService->stats();

        return response()->json($stats);
    }
}
```

---

## Permission Assignment

### Assign Permissions to Roles

```php
use Enadstack\LaravelRoles\Services\RoleService;
use Enadstack\LaravelRoles\Models\Role;

class RolePermissionController extends Controller
{
    public function __construct(
        protected RoleService $roleService
    ) {}

    // Assign permissions to a role
    public function assignPermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permission_ids' => 'required|array|min:1',
            'permission_ids.*' => 'required|integer|exists:permissions,id',
        ]);

        $role = $this->roleService->assignPermissions(
            $role, 
            $validated['permission_ids']
        );

        return response()->json([
            'message' => 'Permissions assigned successfully',
            'role' => $role->load('permissions')
        ]);
    }

    // Get role's permissions
    public function getRolePermissions(int $roleId)
    {
        $role = $this->roleService->getRoleWithPermissions($roleId);

        if (!$role) {
            return response()->json([
                'message' => 'Role not found'
            ], 404);
        }

        return response()->json([
            'role' => $role->name,
            'permissions' => $role->permissions
        ]);
    }

    // Get all permissions grouped by role
    public function permissionsGroupedByRole()
    {
        $grouped = $this->roleService->getPermissionsGroupedByRole();

        return response()->json($grouped);
    }
}
```

### Using in Models

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    // Assign role to user
    public function assignEditorRole()
    {
        $this->assignRole('editor');
    }

    // Check if user has permission
    public function canPublishPosts()
    {
        return $this->hasPermissionTo('posts.publish');
    }

    // Check if user has role
    public function isEditor()
    {
        return $this->hasRole('editor');
    }

    // Get user's permissions
    public function getUserPermissions()
    {
        return $this->getAllPermissions();
    }
}
```

---

## Bulk Operations

### Bulk Delete and Restore

```php
use Enadstack\LaravelRoles\Services\RoleService;

class BulkRoleController extends Controller
{
    public function __construct(
        protected RoleService $roleService
    ) {}

    // Bulk delete roles
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:50',
            'ids.*' => 'required|integer|exists:roles,id',
        ]);

        $results = $this->roleService->bulkDelete($validated['ids']);

        return response()->json([
            'message' => 'Bulk delete operation completed',
            'results' => $results,
            'summary' => [
                'total' => count($validated['ids']),
                'success' => count($results['success']),
                'failed' => count($results['failed'])
            ]
        ]);
    }

    // Bulk restore roles
    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:50',
            'ids.*' => 'required|integer',
        ]);

        $results = $this->roleService->bulkRestore($validated['ids']);

        return response()->json([
            'message' => 'Bulk restore operation completed',
            'results' => $results,
            'summary' => [
                'total' => count($validated['ids']),
                'success' => count($results['success']),
                'failed' => count($results['failed'])
            ]
        ]);
    }

    // Handle partial failures
    public function bulkDeleteWithNotification(Request $request)
    {
        $results = $this->roleService->bulkDelete($request->ids);

        if (!empty($results['failed'])) {
            // Log failures
            foreach ($results['failed'] as $failure) {
                \Log::warning("Failed to delete role {$failure['id']}: {$failure['reason']}");
            }

            // Notify admin
            // AdminNotification::send(...)
        }

        return response()->json($results);
    }
}
```

---

## Statistics and Analytics

### Dashboard Statistics

```php
use Enadstack\LaravelRoles\Services\RoleService;
use Enadstack\LaravelRoles\Services\PermissionService;

class DashboardController extends Controller
{
    public function __construct(
        protected RoleService $roleService,
        protected PermissionService $permissionService
    ) {}

    // Get complete ACL statistics
    public function aclStats()
    {
        $roleStats = $this->roleService->stats();
        $permissionStats = $this->permissionService->stats();
        $recentRoles = $this->roleService->recent(5);
        $recentPermissions = $this->permissionService->recent(5);

        return response()->json([
            'roles' => $roleStats,
            'permissions' => $permissionStats,
            'recent' => [
                'roles' => $recentRoles,
                'permissions' => $recentPermissions
            ]
        ]);
    }

    // Get permission matrix for visualization
    public function permissionMatrix()
    {
        $matrix = $this->permissionService->getPermissionMatrix();

        return view('admin.permissions.matrix', compact('matrix'));
    }

    // Get role usage statistics
    public function roleUsageStats()
    {
        $roles = Role::withCount('users')->get();

        return response()->json([
            'roles' => $roles->map(fn($role) => [
                'name' => $role->name,
                'label' => $role->label,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions()->count()
            ])
        ]);
    }
}
```

---

## Multi-tenancy

### Team-Scoped Mode

```php
// Middleware to set current team
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetPermissionTeam
{
    public function handle(Request $request, Closure $next)
    {
        // Get current team from authenticated user
        $teamId = $request->user()?->current_team_id;

        if ($teamId) {
            // Set team context for Spatie Permission
            app()->instance('permission.team_id', $teamId);
        }

        return $next($request);
    }
}
```

```php
// Register middleware
// app/Http/Kernel.php
protected $routeMiddleware = [
    'permission.team' => \App\Http\Middleware\SetPermissionTeam::class,
];

// config/roles.php
'routes' => [
    'middleware' => ['api', 'auth:sanctum', 'permission.team'],
];
```

### Creating Team-Specific Roles

```php
use Enadstack\LaravelRoles\Services\RoleService;

class TeamRoleController extends Controller
{
    public function createTeamRole(Request $request, RoleService $roleService)
    {
        // Set team context
        $teamId = $request->user()->current_team_id;
        app()->instance('permission.team_id', $teamId);

        // Create role for this team
        $role = $roleService->create([
            'name' => 'team-manager',
            'label' => ['en' => 'Team Manager'],
            'team_id' => $teamId // If using team_scoped mode
        ]);

        return response()->json($role);
    }
}
```

---

## Frontend Integration

### Vue.js Example

```vue
<template>
  <div class="roles-management">
    <div class="stats-cards">
      <StatCard 
        v-for="stat in stats" 
        :key="stat.label"
        :label="stat.label"
        :value="stat.value"
      />
    </div>

    <RolesList 
      :roles="roles" 
      @delete="handleDelete"
      @restore="handleRestore"
      @assign-permissions="handleAssignPermissions"
    />

    <PermissionMatrix 
      :matrix="permissionMatrix"
      @toggle="handleTogglePermission"
    />
  </div>
</template>

<script>
export default {
  data() {
    return {
      roles: [],
      stats: {},
      permissionMatrix: null
    }
  },

  async mounted() {
    await this.loadStats()
    await this.loadRoles()
    await this.loadPermissionMatrix()
  },

  methods: {
    async loadStats() {
      const response = await axios.get('/admin/acl/roles-stats')
      this.stats = response.data
    },

    async loadRoles() {
      const response = await axios.get('/admin/acl/roles', {
        params: { per_page: 50 }
      })
      this.roles = response.data.data
    },

    async loadPermissionMatrix() {
      const response = await axios.get('/admin/acl/permissions-matrix')
      this.permissionMatrix = response.data
    },

    async handleDelete(roleId) {
      await axios.delete(`/admin/acl/roles/${roleId}`)
      await this.loadRoles()
    },

    async handleRestore(roleId) {
      await axios.post(`/admin/acl/roles/${roleId}/restore`)
      await this.loadRoles()
    },

    async handleAssignPermissions(roleId, permissionIds) {
      await axios.post(`/admin/acl/roles/${roleId}/permissions`, {
        permission_ids: permissionIds
      })
      await this.loadPermissionMatrix()
    },

    async handleTogglePermission(roleId, permissionId, hasPermission) {
      // Get current permissions
      const response = await axios.get(`/admin/acl/roles/${roleId}/permissions`)
      const currentPermissions = response.data.map(p => p.id)
      
      // Toggle permission
      const newPermissions = hasPermission
        ? currentPermissions.filter(id => id !== permissionId)
        : [...currentPermissions, permissionId]
      
      // Update
      await this.handleAssignPermissions(roleId, newPermissions)
    }
  }
}
</script>
```

### React Example

```jsx
import { useState, useEffect } from 'react';
import axios from 'axios';

function RolesManagement() {
  const [roles, setRoles] = useState([]);
  const [stats, setStats] = useState({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    setLoading(true);
    try {
      const [rolesRes, statsRes] = await Promise.all([
        axios.get('/admin/acl/roles'),
        axios.get('/admin/acl/roles-stats')
      ]);
      setRoles(rolesRes.data.data);
      setStats(statsRes.data);
    } catch (error) {
      console.error('Error loading data:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleBulkDelete = async (ids) => {
    try {
      const response = await axios.post('/admin/acl/roles/bulk-delete', { ids });
      
      if (response.data.results.failed.length > 0) {
        alert(`Some roles could not be deleted: ${response.data.results.failed.length}`);
      }
      
      await loadData();
    } catch (error) {
      console.error('Bulk delete failed:', error);
    }
  };

  if (loading) return <div>Loading...</div>;

  return (
    <div className="roles-management">
      <div className="stats">
        <StatCard label="Total Roles" value={stats.total} />
        <StatCard label="Active Roles" value={stats.active} />
        <StatCard label="Deleted Roles" value={stats.deleted} />
      </div>

      <RolesList 
        roles={roles}
        onBulkDelete={handleBulkDelete}
      />
    </div>
  );
}

export default RolesManagement;
```

---

## Command Line Examples

### Artisan Commands

```bash
# Install package
php artisan roles:install

# Sync roles and permissions from config
php artisan roles:sync

# Preview changes without applying them
php artisan roles:sync --dry-run

# Remove permissions not in config
php artisan roles:sync --prune

# Clear permission cache
php artisan permission:cache-reset

# Seed roles and permissions
php artisan db:seed --class="Enadstack\LaravelRoles\Database\Seeders\RolesSeeder"
```

### Creating Custom Seeders

```php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;

class CustomRolesSeeder extends Seeder
{
    public function run()
    {
        // Create custom roles
        $contentManager = Role::create([
            'name' => 'content-manager',
            'guard_name' => 'web',
            'label' => [
                'en' => 'Content Manager',
                'ar' => 'مدير المحتوى'
            ]
        ]);

        // Create custom permissions
        $permissions = [
            'posts.create',
            'posts.edit',
            'posts.publish',
            'posts.delete'
        ];

        foreach ($permissions as $permissionName) {
            Permission::create([
                'name' => $permissionName,
                'guard_name' => 'web',
                'group' => 'posts'
            ]);
        }

        // Assign permissions to role
        $contentManager->givePermissionTo($permissions);
    }
}
```

---

## Testing

### Feature Tests

```php
namespace Tests\Feature;

use Tests\TestCase;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_roles()
    {
        Role::factory()->count(5)->create();

        $response = $this->getJson('/admin/acl/roles');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_create_role()
    {
        $data = [
            'name' => 'test-role',
            'label' => ['en' => 'Test Role']
        ];

        $response = $this->postJson('/admin/acl/roles', $data);

        $response->assertStatus(201)
                 ->assertJson(['name' => 'test-role']);
    }

    public function test_can_assign_permissions_to_role()
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $permissions = Permission::factory()->count(3)->create();

        $response = $this->postJson("/admin/acl/roles/{$role->id}/permissions", [
            'permission_ids' => $permissions->pluck('id')->toArray()
        ]);

        $response->assertStatus(200);
        $this->assertEquals(3, $role->fresh()->permissions()->count());
    }

    public function test_bulk_delete_roles()
    {
        $roles = Role::factory()->count(3)->create();
        $ids = $roles->pluck('id')->toArray();

        $response = $this->postJson('/admin/acl/roles/bulk-delete', [
            'ids' => $ids
        ]);

        $response->assertStatus(200);
        $this->assertEquals(3, Role::onlyTrashed()->count());
    }
}
```

---

## See Also

- [API Reference](API_REFERENCE.md) - Complete API documentation
- [README](README.md) - Installation and configuration
- [Endpoints Summary](ENDPOINTS.md) - Quick endpoint reference

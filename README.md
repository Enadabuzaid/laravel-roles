# Laravel Roles & Permissions Package

A comprehensive, reusable Laravel package for managing roles and permissions with support for multi-tenancy, internationalization, and soft deletes. Built on top of [Spatie's Laravel Permission](https://github.com/spatie/laravel-permission) package with additional features and clean service-layer architecture.

## Features

- ✅ **Complete CRUD Operations** for roles and permissions
- ✅ **Soft Delete Support** with restore and force delete capabilities
- ✅ **Bulk Operations** (bulk delete, bulk restore)
- ✅ **Service Layer Architecture** for clean, maintainable code
- ✅ **Multi-tenancy Support** (single, team-scoped, multi-database)
- ✅ **Internationalization (i18n)** with multi-language labels
- ✅ **Permission Grouping** for better organization
- ✅ **Statistics & Analytics** for roles and permissions
- ✅ **Permission Matrix** visualization (roles × permissions)
- ✅ **Recent Items** tracking
- ✅ **RESTful API** with comprehensive endpoints

## Requirements

- PHP >= 8.2
- Laravel >= 12.0
- spatie/laravel-permission >= 6.0

## Installation

1. Install the package via Composer:

```bash
composer require enadstack/laravel-roles
```

2. Run the installation command:

```bash
php artisan roles:install
```

This interactive command will:
- Publish configuration files
- Configure i18n settings
- Set up tenancy mode
- Run migrations
- Optionally seed initial roles and permissions

3. Publish configuration (optional, if you didn't use install command):

```bash
php artisan vendor:publish --tag=roles-config
php artisan vendor:publish --tag=roles-migrations
```

## Configuration

The package configuration is located at `config/roles.php`. Key configuration options:

```php
return [
    // Multi-language support
    'i18n' => [
        'enabled' => false,
        'locales' => ['en'],
        'default' => 'en',
        'fallback' => 'en',
    ],

    // Default guard
    'guard' => env('ROLES_GUARD', 'web'),

    // Tenancy mode: 'single', 'team_scoped', or 'multi_database'
    'tenancy' => [
        'mode' => 'single',
        'team_foreign_key' => 'team_id',
        'provider' => null,
    ],

    // Routes configuration
    'routes' => [
        'prefix' => 'admin/acl',
        'middleware' => ['api', 'auth:sanctum'],
        'guard' => env('ROLES_GUARD', 'web'),
    ],

    // Seed data
    'seed' => [
        'roles' => ['manager'],
        'permission_groups' => [
            'roles' => ['list', 'create', 'show', 'update', 'delete', 'restore', 'force-delete'],
            'users' => ['list', 'create', 'show', 'update', 'delete', 'restore', 'force-delete'],
            'permissions' => ['list', 'show']
        ],
        'map' => [
            'super-admin' => ['*'],
            'admin' => ['users.*'],
        ],
    ],
];
```

## API Endpoints

All endpoints are prefixed with the value configured in `routes.prefix` (default: `admin/acl`).

### Roles

#### List Roles
```http
GET /admin/acl/roles?search=admin&sort=created_at&direction=desc&per_page=20
```

#### Get Role Details
```http
GET /admin/acl/roles/{id}
```

#### Create Role
```http
POST /admin/acl/roles
Content-Type: application/json

{
    "name": "editor",
    "label": {"en": "Editor", "ar": "محرر"},
    "description": {"en": "Content editor role", "ar": "دور محرر المحتوى"}
}
```

#### Update Role
```http
PUT /admin/acl/roles/{id}
Content-Type: application/json

{
    "name": "senior-editor",
    "label": {"en": "Senior Editor"}
}
```

#### Soft Delete Role
```http
DELETE /admin/acl/roles/{id}
```

#### Force Delete Role (Permanent)
```http
DELETE /admin/acl/roles/{id}/force
```

#### Restore Role
```http
POST /admin/acl/roles/{id}/restore
```

#### Bulk Delete Roles
```http
POST /admin/acl/roles/bulk-delete
Content-Type: application/json

{
    "ids": [1, 2, 3]
}
```

#### Bulk Restore Roles
```http
POST /admin/acl/roles/bulk-restore
Content-Type: application/json

{
    "ids": [1, 2, 3]
}
```

#### Get Recent Roles
```http
GET /admin/acl/roles-recent?limit=10
```

#### Get Role Statistics
```http
GET /admin/acl/roles-stats
```

Response:
```json
{
    "total": 10,
    "active": 8,
    "deleted": 2,
    "with_permissions": 6,
    "without_permissions": 4
}
```

#### Assign Permissions to Role
```http
POST /admin/acl/roles/{id}/permissions
Content-Type: application/json

{
    "permission_ids": [1, 2, 3, 4]
}
```

#### Get Role Permissions
```http
GET /admin/acl/roles/{id}/permissions
```

#### Get Permissions Grouped by Role
```http
GET /admin/acl/roles-permissions
```

### Permissions

#### List Permissions
```http
GET /admin/acl/permissions?search=user&group=users&sort=name&direction=asc&per_page=20
```

#### Get Permission Details
```http
GET /admin/acl/permissions/{id}
```

#### Create Permission
```http
POST /admin/acl/permissions
Content-Type: application/json

{
    "name": "posts.publish",
    "group": "posts",
    "label": {"en": "Publish Posts", "ar": "نشر المنشورات"},
    "description": {"en": "Allow publishing posts"},
    "group_label": {"en": "Posts", "ar": "المنشورات"}
}
```

#### Update Permission
```http
PUT /admin/acl/permissions/{id}
Content-Type: application/json

{
    "label": {"en": "Publish and Schedule Posts"}
}
```

#### Soft Delete Permission
```http
DELETE /admin/acl/permissions/{id}
```

#### Force Delete Permission (Permanent)
```http
DELETE /admin/acl/permissions/{id}/force
```

#### Restore Permission
```http
POST /admin/acl/permissions/{id}/restore
```

#### Get Recent Permissions
```http
GET /admin/acl/permissions-recent?limit=10
```

#### Get Permission Statistics
```http
GET /admin/acl/permissions-stats
```

Response:
```json
{
    "total": 50,
    "active": 45,
    "deleted": 5,
    "assigned": 40,
    "unassigned": 10,
    "by_group": {
        "users": 7,
        "roles": 7,
        "posts": 10,
        "comments": 5
    }
}
```

#### Get Permission Matrix (Roles × Permissions)
```http
GET /admin/acl/permissions-matrix
```

Response:
```json
{
    "roles": [
        {"id": 1, "name": "super-admin", "label": {"en": "Super Admin"}},
        {"id": 2, "name": "admin", "label": {"en": "Admin"}}
    ],
    "matrix": [
        {
            "permission_id": 1,
            "permission_name": "users.list",
            "permission_label": {"en": "List Users"},
            "permission_group": "users",
            "roles": {
                "super-admin": {"role_id": 1, "has_permission": true},
                "admin": {"role_id": 2, "has_permission": true}
            }
        }
    ]
}
```

#### Get Permission Groups
```http
GET /admin/acl/permission-groups
```

## Service Layer Usage

The package provides service classes for cleaner code and better testability:

### RoleService

```php
use Enadstack\LaravelRoles\Services\RoleService;

class YourController extends Controller
{
    protected RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function example()
    {
        // List roles
        $roles = $this->roleService->list(['search' => 'admin'], 20);

        // Create role
        $role = $this->roleService->create([
            'name' => 'editor',
            'label' => ['en' => 'Editor']
        ]);

        // Update role
        $this->roleService->update($role, ['name' => 'senior-editor']);

        // Delete role (soft)
        $this->roleService->delete($role);

        // Restore role
        $this->roleService->restore($roleId);

        // Force delete
        $this->roleService->forceDelete($role);

        // Bulk operations
        $results = $this->roleService->bulkDelete([1, 2, 3]);
        $results = $this->roleService->bulkRestore([1, 2, 3]);

        // Get recent roles
        $recent = $this->roleService->recent(10);

        // Get statistics
        $stats = $this->roleService->stats();

        // Assign permissions
        $this->roleService->assignPermissions($role, [1, 2, 3]);

        // Get permissions grouped by role
        $grouped = $this->roleService->getPermissionsGroupedByRole();
    }
}
```

### PermissionService

```php
use Enadstack\LaravelRoles\Services\PermissionService;

class YourController extends Controller
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function example()
    {
        // List permissions
        $permissions = $this->permissionService->list(['group' => 'users'], 20);

        // Create permission
        $permission = $this->permissionService->create([
            'name' => 'posts.publish',
            'group' => 'posts'
        ]);

        // Get recent permissions
        $recent = $this->permissionService->recent(10);

        // Get statistics
        $stats = $this->permissionService->stats();

        // Get permission matrix
        $matrix = $this->permissionService->getPermissionMatrix();

        // Get grouped permissions
        $grouped = $this->permissionService->getGroupedPermissions();
    }
}
```

## Multi-tenancy Support

The package supports three tenancy modes:

### 1. Single Project (No Multi-tenancy)
Default mode. All roles and permissions are shared across the application.

### 2. Team Scoped (Same Database)
Roles and permissions can be scoped to specific teams/tenants using a foreign key.

```php
// In your middleware or service provider
app()->instance('permission.team_id', $currentTeamId);
```

### 3. Multi-Database
Each tenant has its own database. Works with packages like `stancl/tenancy`.

## Commands

### Install Package
```bash
php artisan roles:install
```

### Sync Roles & Permissions
```bash
# Sync from config
php artisan roles:sync

# Dry run (preview changes)
php artisan roles:sync --dry-run

# Sync without mapping
php artisan roles:sync --no-map

# Remove permissions not in config
php artisan roles:sync --prune
```

## Seeding

The package includes a seeder that creates roles and permissions from your configuration:

```php
php artisan db:seed --class="Enadstack\LaravelRoles\Database\Seeders\RolesSeeder"
```

## Models

### Role Model

The package extends Spatie's Role model with soft deletes:

```php
use Enadstack\LaravelRoles\Models\Role;

// Query with soft deletes
$activeRoles = Role::all();
$deletedRoles = Role::onlyTrashed()->get();
$allRoles = Role::withTrashed()->get();
```

### Permission Model

The package extends Spatie's Permission model with soft deletes and grouping:

```php
use Enadstack\LaravelRoles\Models\Permission;

// Query by group
$userPermissions = Permission::where('group', 'users')->get();

// With soft deletes
$activePermissions = Permission::all();
$deletedPermissions = Permission::onlyTrashed()->get();
```

## Testing

The package uses Orchestra Testbench for testing:

```bash
composer test
```

## Best Practices

1. **Use the Service Layer**: Always use service classes instead of directly accessing models in controllers.

2. **Validate Input**: Use Laravel's validation rules for all user inputs.

3. **Handle Bulk Operations Carefully**: Bulk operations return success/failure arrays. Always check the results.

4. **Cache Permission Checks**: Use Spatie's built-in caching for permission checks.

5. **Reset Cache After Changes**: Run `php artisan permission:cache-reset` after bulk changes.

6. **Use Permission Groups**: Organize permissions into logical groups for better UI and management.

7. **Multi-language Support**: If using i18n, always provide translations for all configured locales.

## Security Considerations

1. **Protect Endpoints**: Always add authentication and authorization middleware to routes.

2. **Validate Bulk Operations**: Limit the number of IDs in bulk operations to prevent abuse.

3. **Audit Trail**: Consider logging all role and permission changes for security auditing.

4. **Force Delete**: Restrict force delete operations to super admins only.

5. **Tenant Isolation**: In multi-tenant setups, ensure proper tenant scoping is enforced.

## License

MIT License

## Credits

- Built on [Spatie Laravel Permission](https://github.com/spatie/laravel-permission)
- Developed by [Enad Abuzaid](mailto:enad.abuzaid15@gmail.com)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

For issues and questions, please use the [GitHub issue tracker](https://github.com/Enadabuzaid/laravel-roles/issues).

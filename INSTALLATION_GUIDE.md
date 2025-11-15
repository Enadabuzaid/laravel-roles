# Laravel Roles & Permissions Package - Complete Installation & Usage Guide

**Version**: 1.0.0  
**Package**: `enadstack/laravel-roles`  
**Based on**: Spatie Laravel Permission v6.0  
**Laravel**: >= 12.0 | PHP >= 8.2

---

## 📦 What This Package Provides

### ✅ Complete Feature List

#### **Roles Management**
- ✅ List roles (paginated, searchable, sortable)
- ✅ Create role with multi-language labels/descriptions
- ✅ Show single role details
- ✅ Update role
- ✅ Soft delete role
- ✅ Force delete role (permanent)
- ✅ Restore soft-deleted role
- ✅ Bulk delete roles
- ✅ Bulk restore roles  
- ✅ Bulk force delete roles
- ✅ Clone role with permissions
- ✅ Assign/sync permissions to role
- ✅ Add single permission to role
- ✅ Remove single permission from role
- ✅ Get role statistics
- ✅ Get recently created roles
- ✅ Get permissions grouped by role

#### **Permissions Management**
- ✅ List permissions (paginated, searchable, filterable by group)
- ✅ Create permission with group/labels
- ✅ Show single permission details
- ✅ Update permission
- ✅ Soft delete permission
- ✅ Force delete permission (permanent)
- ✅ Restore soft-deleted permission
- ✅ Bulk force delete permissions
- ✅ Get permission statistics
- ✅ Get recently created permissions
- ✅ Get grouped permissions (by group)
- ✅ Get permission matrix (roles × permissions grid)

#### **Additional Features**
- ✅ Multi-language support (i18n) for labels/descriptions
- ✅ Multi-tenancy support (single, team-scoped, multi-database)
- ✅ Permission grouping (e.g., users, roles, posts)
- ✅ Config-based permission seeding
- ✅ Sync command to update permissions from config
- ✅ Service layer architecture (RoleService, PermissionService)
- ✅ API Resources for consistent responses
- ✅ Form Request validation
- ✅ Domain events (RoleCreated, PermissionUpdated, etc.)
- ✅ Cache support with automatic invalidation
- ✅ Current user ACL endpoints (`/me/roles`, `/me/permissions`)

---

## 🚀 Installation (Step-by-Step)

### Step 1: Install via Composer

```bash
composer require enadstack/laravel-roles
```

### Step 2: Run Interactive Installer

```bash
php artisan roles:install
```

**The installer will:**
1. Publish Spatie Permission config & migrations
2. Publish package config (`config/roles.php`)
3. Ask if you want multi-language support (i18n)
4. Ask which tenancy mode (single / team-scoped / multi-database)
5. Run migrations
6. Optionally seed initial roles & permissions

**Installer Options:**
- `--with-seeds`: Auto-seed without prompting
- `--team-key=tenant_id`: Custom tenant foreign key for team-scoped mode

### Step 3: Configure (Optional)

Edit `config/roles.php` to customize:

```php
return [
    // Multi-language support
    'i18n' => [
        'enabled' => false,  // Set to true for multi-language
        'locales' => ['en', 'ar'],
        'default' => 'en',
    ],

    'guard' => 'web',

    // Tenancy mode
    'tenancy' => [
        'mode' => 'single',  // 'single' | 'team_scoped' | 'multi_database'
        'team_foreign_key' => 'team_id',
    ],

    // API Routes
    'routes' => [
        'prefix' => 'admin/acl',
        'middleware' => ['api', 'auth:sanctum'],
        'expose_me' => true,  // Exposes /me/roles, /me/permissions endpoints
    ],

    // Cache
    'cache' => [
        'enabled' => true,
        'ttl' => 300,  // seconds
    ],

    // Seed data (roles & permissions)
    'seed' => [
        'roles' => ['manager'],
        'permission_groups' => [
            'roles' => ['list', 'create', 'show', 'update', 'delete', 'restore', 'force-delete'],
            'users' => ['list', 'create', 'show', 'update', 'delete'],
            'permissions' => ['list', 'show'],
        ],
        'map' => [
            'super-admin' => ['*'],  // All permissions
            'admin' => ['users.*'],  // All user permissions
        ],
    ],
];
```

### Step 4: Run Migrations

If you didn't use the installer:

```bash
php artisan migrate
```

### Step 5: Seed Data (Optional)

```bash
php artisan db:seed --class="Enadstack\LaravelRoles\Database\Seeders\RolesSeeder"
```

---

## 🔧 Adding New Permissions Later

### Option A: Via API (Quick)

```bash
POST /admin/acl/permissions
{
  "name": "offers.create",
  "group": "offers",
  "label": {"en": "Create Offers"},
  "description": {"en": "Allow creating offers"}
}
```

### Option B: Via Config (Recommended for Production)

**1. Edit `config/roles.php`:**

```php
'seed' => [
    'permission_groups' => [
        // ...existing groups...
        'offers' => ['list', 'create', 'update', 'delete', 'show'],
    ],
    'map' => [
        'admin' => ['offers.*'],  // Give admin all offer permissions
    ],
],
```

**2. Run sync command:**

```bash
php artisan roles:sync
```

**Options:**
- `--dry-run`: Preview changes without applying
- `--prune`: Remove permissions not in config
- `--no-map`: Skip role->permission mapping
- `--team-id=X`: For team-scoped tenancy

**3. Clear cache:**

```bash
php artisan permission:cache-reset
```

---

## 📡 API Endpoints

All endpoints use the prefix from config (default: `/admin/acl`).

### Roles

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/roles` | List roles (paginated) |
| POST | `/roles` | Create role |
| GET | `/roles/{id}` | Show role |
| PUT | `/roles/{id}` | Update role |
| DELETE | `/roles/{id}` | Soft delete role |
| DELETE | `/roles/{id}/force` | Force delete role |
| POST | `/roles/{id}/restore` | Restore role |
| POST | `/roles/bulk-delete` | Bulk delete `{ids: []}` |
| POST | `/roles/bulk-restore` | Bulk restore `{ids: []}` |
| POST | `/roles/bulk-force-delete` | Bulk force delete `{ids: []}` |
| GET | `/roles-recent?limit=10` | Recent roles |
| GET | `/roles-stats` | Role statistics |
| POST | `/roles/{id}/permissions` | Assign permissions `{permission_ids: []}` |
| GET | `/roles/{id}/permissions` | Get role permissions |
| GET | `/roles-permissions` | Permissions grouped by role |
| POST | `/roles/{role}/permission` | Add single permission `{permission_id: X}` |
| DELETE | `/roles/{role}/permission` | Remove single permission `{permission_id: X}` |
| POST | `/roles/{role}/clone` | Clone role `{name: "new-name"}` |

### Permissions

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/permissions?group=users` | List permissions (filterable) |
| POST | `/permissions` | Create permission |
| GET | `/permissions/{id}` | Show permission |
| PUT | `/permissions/{id}` | Update permission |
| DELETE | `/permissions/{id}` | Soft delete permission |
| DELETE | `/permissions/{id}/force` | Force delete permission |
| POST | `/permissions/{id}/restore` | Restore permission |
| POST | `/permissions/bulk-force-delete` | Bulk force delete `{ids: []}` |
| GET | `/permissions-recent?limit=10` | Recent permissions |
| GET | `/permissions-stats` | Permission statistics |
| GET | `/permission-groups` | Grouped permissions |
| GET | `/permissions-matrix` | Permission matrix (roles × permissions) |

### Current User (if `expose_me` is true)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/me/roles` | Current user's roles |
| GET | `/me/permissions` | Current user's permissions |
| GET | `/me/abilities` | Current user's abilities |

---

## 💻 Service Layer Usage

### RoleService

```php
use Enadstack\LaravelRoles\Services\RoleService;

class MyController extends Controller
{
    public function __construct(protected RoleService $roleService) {}

    public function example()
    {
        // List
        $roles = $this->roleService->list(['search' => 'admin'], 20);

        // Create
        $role = $this->roleService->create([
            'name' => 'editor',
            'label' => ['en' => 'Editor'],
        ]);

        // Update
        $this->roleService->update($role, ['name' => 'senior-editor']);

        // Assign permissions
        $this->roleService->assignPermissions($role, [1, 2, 3]);

        // Statistics
        $stats = $this->roleService->stats();
        // Returns: {total, active, deleted, with_permissions, without_permissions}

        // Clone
        $newRole = $this->roleService->cloneWithPermissions($role, 'new-name');
    }
}
```

### PermissionService

```php
use Enadstack\LaravelRoles\Services\PermissionService;

class MyController extends Controller
{
    public function __construct(protected PermissionService $permissionService) {}

    public function example()
    {
        // List with filters
        $perms = $this->permissionService->list([
            'search' => 'user',
            'group' => 'users',
        ], 20);

        // Create
        $perm = $this->permissionService->create([
            'name' => 'posts.publish',
            'group' => 'posts',
            'label' => ['en' => 'Publish Posts'],
        ]);

        // Permission Matrix
        $matrix = $this->permissionService->getPermissionMatrix();
        // Returns: {roles: [], matrix: []}

        // Grouped permissions
        $grouped = $this->permissionService->getGroupedPermissions();
    }
}
```

---

## 🎯 Events

Listen to domain events for audit logs, webhooks, etc.:

```php
use Enadstack\LaravelRoles\Events\RoleCreated;
use Enadstack\LaravelRoles\Events\RoleUpdated;
use Enadstack\LaravelRoles\Events\RoleDeleted;
use Enadstack\LaravelRoles\Events\PermissionCreated;
use Enadstack\LaravelRoles\Events\PermissionUpdated;
use Enadstack\LaravelRoles\Events\PermissionsAssignedToRole;

// In EventServiceProvider
protected $listen = [
    RoleCreated::class => [
        AuditLogListener::class,
    ],
];
```

---

## 🏢 Multi-Tenancy Setup

### Team-Scoped Mode

**1. Configure:**

```php
// config/roles.php
'tenancy' => [
    'mode' => 'team_scoped',
    'team_foreign_key' => 'tenant_id',  // or 'team_id', 'provider_id'
],
```

**2. Add Middleware:**

```php
// app/Http/Kernel.php or bootstrap/app.php
use Enadstack\LaravelRoles\Http\Middleware\SetPermissionTeamId;

protected $middlewareAliases = [
    'set.tenant' => SetPermissionTeamId::class,
];

// Apply to routes
Route::middleware(['auth:sanctum', 'set.tenant'])->group(...);
```

**3. Seed per tenant:**

```bash
php artisan roles:sync --team-id=123
```

### Multi-Database Mode

**1. Configure:**

```php
'tenancy' => [
    'mode' => 'multi_database',
    'provider' => 'stancl/tenancy',
],
```

**2. Run migrations on each tenant DB:**

```bash
php artisan tenants:artisan "migrate --path=database/migrations"
```

---

## 🔒 Securing Routes

```php
// Protect your routes
Route::middleware(['auth:sanctum', 'permission:roles.create'])->group(function () {
    Route::post('/roles', [RoleController::class, 'store']);
});

// Or use roles
Route::middleware(['auth:sanctum', 'role:admin'])->group(...);
```

---

## 🧪 Testing

```bash
./vendor/bin/pest
```

**Coverage:**
- ✅ 17+ integration tests
- ✅ Role CRUD + bulk operations
- ✅ Permission CRUD + filtering
- ✅ Permission matrix
- ✅ Sync command
- ✅ Event dispatching
- ✅ Validation

---

## 📋 CI/CD Deployment

```bash
# In your deployment script
php artisan migrate --force
php artisan roles:sync --no-interaction
php artisan permission:cache-reset
```

---

## 🐛 Troubleshooting

### Cache Issues

```bash
php artisan permission:cache-reset
php artisan cache:clear
```

### Migration Order Issues

Ensure Spatie migrations run before package migrations. The installer handles this automatically.

### Team-Scoped Not Working

Make sure you set the team context:

```php
app()->instance('permission.team_id', $tenantId);
```

Or use the provided middleware.

---

## 📚 Additional Resources

- **Spatie Permission Docs**: https://spatie.be/docs/laravel-permission
- **API Reference**: See `API_REFERENCE.md`
- **Usage Examples**: See `USAGE_EXAMPLES.md`
- **Changelog**: See `CHANGELOG.md`

---

## ✨ What Makes This Package Production-Ready

1. **Service Layer** - Clean, testable business logic
2. **FormRequests** - Centralized validation
3. **API Resources** - Consistent JSON responses
4. **Events** - Extensible audit trails
5. **Multi-tenancy** - Enterprise-ready
6. **Sync Command** - Config-driven deployment
7. **Soft Deletes** - Data safety
8. **Cache** - Performance optimized
9. **Tests** - Quality assurance
10. **i18n** - Global-ready

---

## 📝 Quick Start Example

```bash
# Install
composer require enadstack/laravel-roles
php artisan roles:install

# Add permissions
# Edit config/roles.php, add:
# 'offers' => ['list', 'create', 'update', 'delete']

# Sync
php artisan roles:sync

# Use API
curl -X GET http://yourapp.test/admin/acl/permissions-matrix \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

**Package Status**: ✅ Production Ready v1.0  
**Author**: Enad Abuzaid  
**License**: MIT


# Quick Reference Guide - Laravel Roles & Permissions

A cheat sheet for quick access to common operations.

---

## 🚀 Installation (3 Steps)

```bash
composer require enadstack/laravel-roles
php artisan roles:install
# Follow the interactive prompts
```

---

## 📋 Common Commands

```bash
# Sync permissions from config
php artisan roles:sync

# Dry run (see what will happen)
php artisan roles:sync --dry-run

# Prune permissions not in config
php artisan roles:sync --prune

# Sync for specific tenant
php artisan roles:sync --team-id=123

# Clear permission cache
php artisan permission:cache-reset
```

---

## 🔧 Quick Configuration

**Enable i18n:**
```php
// config/roles.php
'i18n' => ['enabled' => true],
```

**Enable team-scoped tenancy:**
```php
'tenancy' => ['mode' => 'team_scoped', 'team_foreign_key' => 'tenant_id'],
```

**Change route prefix:**
```php
'routes' => ['prefix' => 'api/admin/acl'],
```

---

## 📡 API Quick Reference

### Roles

```bash
# List
GET /admin/acl/roles?search=admin&per_page=20

# Create
POST /admin/acl/roles
{"name": "editor", "label": {"en": "Editor"}}

# Update
PUT /admin/acl/roles/1
{"name": "senior-editor"}

# Delete (soft)
DELETE /admin/acl/roles/1

# Restore
POST /admin/acl/roles/1/restore

# Force Delete (permanent)
DELETE /admin/acl/roles/1/force

# Bulk Delete
POST /admin/acl/roles/bulk-delete
{"ids": [1, 2, 3]}

# Assign Permissions
POST /admin/acl/roles/1/permissions
{"permission_ids": [1, 2, 3]}

# Clone Role
POST /admin/acl/roles/1/clone
{"name": "new-role-name"}

# Statistics
GET /admin/acl/roles-stats

# Recent Roles
GET /admin/acl/roles-recent?limit=10
```

### Permissions

```bash
# List
GET /admin/acl/permissions?group=users&search=create

# Create
POST /admin/acl/permissions
{"name": "posts.create", "group": "posts"}

# Permission Matrix
GET /admin/acl/permissions-matrix

# Grouped Permissions
GET /admin/acl/permission-groups

# Statistics
GET /admin/acl/permissions-stats
```

### Current User

```bash
GET /admin/acl/me/roles
GET /admin/acl/me/permissions
GET /admin/acl/me/abilities
```

---

## 💻 Service Layer Quick Examples

### RoleService

```php
use Enadstack\LaravelRoles\Services\RoleService;

// Inject in constructor
public function __construct(protected RoleService $roleService) {}

// List
$roles = $this->roleService->list(['search' => 'admin'], 20);

// Create
$role = $this->roleService->create(['name' => 'editor']);

// Update
$role = $this->roleService->update($role, ['name' => 'senior-editor']);

// Delete
$this->roleService->delete($role);

// Restore
$this->roleService->restore($roleId);

// Assign Permissions
$this->roleService->assignPermissions($role, [1, 2, 3]);

// Clone
$cloned = $this->roleService->cloneWithPermissions($role, 'new-name');

// Stats
$stats = $this->roleService->stats();
```

### PermissionService

```php
use Enadstack\LaravelRoles\Services\PermissionService;

// Inject in constructor
public function __construct(protected PermissionService $permissionService) {}

// List
$permissions = $this->permissionService->list(['group' => 'users'], 50);

// Create
$perm = $this->permissionService->create(['name' => 'posts.create']);

// Permission Matrix (cached)
$matrix = $this->permissionService->getPermissionMatrix();

// Grouped (cached)
$grouped = $this->permissionService->getGroupedPermissions();

// Stats
$stats = $this->permissionService->stats();
```

---

## 🔔 Events Quick Reference

```php
use Enadstack\LaravelRoles\Events\{
    RoleCreated,
    RoleUpdated,
    RoleDeleted,
    PermissionCreated,
    PermissionUpdated,
    PermissionsAssignedToRole
};

// Listen in EventServiceProvider
protected $listen = [
    RoleCreated::class => [YourListener::class],
];
```

---

## 🏢 Multi-Tenancy Quick Setup

### Team-Scoped

```php
// 1. Config
'tenancy' => ['mode' => 'team_scoped', 'team_foreign_key' => 'tenant_id'],

// 2. Middleware
Route::middleware(['auth', 'set.tenant'])->group(function () {
    // Routes
});

// 3. In code
setPermissionsTeamId($tenantId);

// 4. Sync
php artisan roles:sync --team-id=123
```

### Multi-Database

```php
// 1. Config
'tenancy' => ['mode' => 'multi_database'],

// 2. Run per tenant
php artisan tenants:artisan "roles:sync"
```

---

## 🔑 Using Roles & Permissions

### In Code

```php
// Assign role
$user->assignRole('admin');
$user->assignRole(['admin', 'editor']);

// Check role
if ($user->hasRole('admin')) {
    //
}

// Assign permission
$user->givePermissionTo('posts.create');

// Check permission
if ($user->can('posts.create')) {
    //
}

// Check multiple
if ($user->hasAnyRole(['admin', 'editor'])) {
    //
}

if ($user->hasAllRoles(['admin', 'super-admin'])) {
    //
}
```

### In Routes

```php
// Protect with permission
Route::middleware(['permission:posts.create'])->group(function () {
    Route::post('/posts', [PostController::class, 'store']);
});

// Protect with role
Route::middleware(['role:admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});

// Protect with role OR permission
Route::middleware(['role_or_permission:admin|posts.create'])->group(function () {
    //
});
```

### In Blade

```blade
@role('admin')
    <p>Admin content</p>
@endrole

@hasrole('admin')
    <p>Admin content</p>
@endhasrole

@can('posts.create')
    <a href="/posts/create">Create Post</a>
@endcan
```

---

## 🎯 Adding New Module Permissions

**Example: Adding "Offers" module**

```php
// 1. Edit config/roles.php
'seed' => [
    'permission_groups' => [
        // ...existing...
        'offers' => ['list', 'create', 'update', 'delete', 'approve'],
    ],
    'map' => [
        'admin' => ['offers.*'],
        'offer-manager' => ['offers.*'],
    ],
],

// 2. Sync
php artisan roles:sync

// 3. Clear cache
php artisan permission:cache-reset

// 4. Use
$user->givePermissionTo('offers.create');
```

---

## 🐛 Troubleshooting

```bash
# Cache issues
php artisan permission:cache-reset
php artisan cache:clear

# See what will be synced
php artisan roles:sync --dry-run

# Check migrations
php artisan migrate:status

# Re-run migrations
php artisan migrate:fresh
php artisan roles:sync
```

---

## 📊 Response Formats

### Role Resource

```json
{
  "data": {
    "id": 1,
    "name": "editor",
    "guard_name": "web",
    "label": {"en": "Editor"},
    "description": {"en": "Content editor"},
    "permissions": [...],
    "created_at": "2025-11-15T10:00:00Z",
    "updated_at": "2025-11-15T10:00:00Z"
  }
}
```

### Permission Resource

```json
{
  "data": {
    "id": 1,
    "name": "posts.create",
    "guard_name": "web",
    "group": "posts",
    "label": {"en": "Create Posts"},
    "created_at": "2025-11-15T10:00:00Z"
  }
}
```

### Statistics

```json
{
  "total": 10,
  "active": 8,
  "deleted": 2,
  "with_permissions": 7,
  "without_permissions": 3
}
```

### Permission Matrix

```json
{
  "data": {
    "roles": [
      {"id": 1, "name": "admin"},
      {"id": 2, "name": "editor"}
    ],
    "matrix": [
      {
        "permission_id": 1,
        "permission_name": "posts.create",
        "roles": {
          "1": true,
          "2": true
        }
      }
    ],
    "generated_at": "2025-11-15T10:00:00Z"
  }
}
```

---

## 🔗 Useful Links

- **Full Documentation:** README.md
- **Installation Guide:** INSTALLATION_GUIDE.md
- **Test Results:** FINAL_TEST_RESULTS.md
- **Spatie Docs:** https://spatie.be/docs/laravel-permission

---

**Version:** 1.0.0  
**Quick Access:** Keep this file handy for daily use!


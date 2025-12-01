# 🚀 Laravel Roles Package - Developer Quick Reference

## 📦 Installation (30 seconds)

```bash
composer require enadstack/laravel-roles
php artisan roles:install
# Follow the interactive prompts
```

---

## ⚡ Quick API Commands

### Create Role
```bash
curl -X POST /admin/acl/roles \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"editor","label":{"en":"Editor"}}'
```

### Create Permission
```bash
curl -X POST /admin/acl/permissions \
  -d '{"name":"posts.create","group":"posts","label":{"en":"Create Posts"}}'
```

### Assign Permissions to Role
```bash
curl -X POST /admin/acl/roles/1/permissions \
  -d '{"permission_ids":[1,2,3,4,5]}'
```

### Get Permission Matrix
```bash
curl -X GET /admin/acl/permissions-matrix
```

---

## 💻 Quick Code Snippets

### Assign Role to User
```php
use Enadstack\LaravelRoles\Models\Role;

$user = auth()->user();
$user->assignRole('editor');
// or
$role = Role::findByName('editor');
$user->assignRole($role);
```

### Check Permissions
```php
// Check role
if ($user->hasRole('editor')) {
    // ...
}

// Check permission
if ($user->can('posts.create')) {
    // ...
}

// Check any role
if ($user->hasAnyRole(['editor', 'admin'])) {
    // ...
}
```

### Blade Directives
```blade
@role('editor')
    <p>You are an editor!</p>
@endrole

@can('posts.create')
    <button>Create Post</button>
@endcan

@hasanyrole('editor|admin')
    <a href="/admin">Admin Panel</a>
@endhasanyrole
```

### Using Service Layer
```php
use Enadstack\LaravelRoles\Services\RoleService;

$roleService = app(RoleService::class);

// Create role
$role = $roleService->create([
    'name' => 'content-manager',
    'label' => ['en' => 'Content Manager'],
]);

// Assign permissions
$roleService->assignPermissions($role, [1, 2, 3]);

// Clone role
$newRole = $roleService->cloneWithPermissions($role, 'content-manager-junior');

// Get stats
$stats = $roleService->stats();
```

---

## 🏢 Multi-Tenancy Setup

### Team-Scoped Mode

**1. Configure:**
```php
// config/roles.php
'tenancy' => [
    'mode' => 'team_scoped',
    'team_foreign_key' => 'team_id',
],
```

**2. Set Context (Middleware):**
```php
// app/Http/Middleware/SetTenantContext.php
public function handle($request, $next)
{
    if ($user = auth()->user()) {
        app()->instance('permission.team_id', $user->team_id);
    }
    return $next($request);
}
```

**3. Register Middleware:**
```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ...
        \App\Http\Middleware\SetTenantContext::class,
    ],
];
```

**4. Use:**
```php
// Automatically scoped to current team + global
$roles = Role::all();

// Bypass scope (super-admin only)
$allRoles = Role::forAllTenants()->get();

// Only tenant-specific
$tenantRoles = Role::onlyTenantSpecific()->get();

// Only global
$globalRoles = Role::onlyGlobal()->get();
```

---

## 🔧 Configuration Quick Reference

```php
// config/roles.php
return [
    // Multi-language support
    'i18n' => [
        'enabled' => false,
        'locales' => ['en', 'ar'],
    ],

    // Default guard
    'guard' => 'web',

    // Tenancy mode: 'single' | 'team_scoped' | 'multi_database'
    'tenancy' => [
        'mode' => 'single',
        'team_foreign_key' => 'team_id',
    ],

    // API routes
    'routes' => [
        'prefix' => 'admin/acl',
        'middleware' => ['api', 'auth:sanctum'],
        'expose_me' => true, // /me/roles, /me/permissions
    ],

    // Cache (5 mins default)
    'cache' => [
        'enabled' => true,
        'ttl' => 300,
    ],

    // Seed data (for roles:sync command)
    'seed' => [
        'permission_groups' => [
            'posts' => ['list', 'create', 'update', 'delete'],
            'users' => ['list', 'show', 'update', 'ban'],
        ],
        'map' => [
            'admin' => ['*'],
            'editor' => ['posts.*'],
        ],
    ],
];
```

---

## 🎯 Common Tasks

### 1. Add New Permission Group

**Option A: Via API**
```bash
POST /admin/acl/permissions
{"name":"offers.create","group":"offers","label":{"en":"Create Offers"}}
{"name":"offers.update","group":"offers","label":{"en":"Update Offers"}}
{"name":"offers.delete","group":"offers","label":{"en":"Delete Offers"}}
```

**Option B: Via Config (CI/CD)**
```php
// config/roles.php
'seed' => [
    'permission_groups' => [
        'offers' => ['list', 'create', 'update', 'delete'],
    ],
],
```
```bash
php artisan roles:sync
```

---

### 2. Clone Existing Role

```php
$roleService = app(RoleService::class);
$existingRole = Role::findByName('editor');

$newRole = $roleService->cloneWithPermissions($existingRole, 'junior-editor', [
    'label' => ['en' => 'Junior Editor'],
    'description' => ['en' => 'Limited editor access'],
]);
```

---

### 3. Bulk Operations

```php
// Bulk delete
$results = $roleService->bulkDelete([1, 2, 3, 4, 5]);
// Returns: ['success' => [1, 2, 3], 'failed' => [['id' => 4, 'reason' => '...']]]

// Bulk restore
$results = $roleService->bulkRestore([1, 2, 3]);

// Bulk force delete (permanent)
$results = $roleService->bulkForceDelete([1, 2, 3]);
```

---

### 4. Implement Audit Logging

```php
// app/Providers/EventServiceProvider.php
use Enadstack\LaravelRoles\Events\RoleCreated;
use Enadstack\LaravelRoles\Events\RoleUpdated;
use Enadstack\LaravelRoles\Events\PermissionsAssignedToRole;

protected $listen = [
    RoleCreated::class => [\App\Listeners\LogRoleCreated::class],
    RoleUpdated::class => [\App\Listeners\LogRoleUpdated::class],
    PermissionsAssignedToRole::class => [\App\Listeners\LogPermissionsAssigned::class],
];

// app/Listeners/LogRoleCreated.php
public function handle(RoleCreated $event): void
{
    \App\Models\AuditLog::create([
        'user_id' => auth()->id(),
        'action' => 'role.created',
        'model_type' => 'role',
        'model_id' => $event->role->id,
        'data' => $event->role->toArray(),
        'ip' => request()->ip(),
    ]);
}
```

---

### 5. Custom Authorization Logic

```php
// app/Policies/CustomRolePolicy.php
namespace App\Policies;

use Enadstack\LaravelRoles\Policies\RolePolicy as BasePolicy;

class CustomRolePolicy extends BasePolicy
{
    public function update(Authenticatable $user, Role $role): bool
    {
        // Add custom logic
        if ($role->name === 'organization-admin') {
            return $user->id === $role->organization->owner_id;
        }
        
        return parent::update($user, $role);
    }
}

// app/Providers/AuthServiceProvider.php
use Enadstack\LaravelRoles\Models\Role;
use App\Policies\CustomRolePolicy;

protected $policies = [
    Role::class => CustomRolePolicy::class,
];
```

---

## 🐛 Troubleshooting

### Issue: 403 Unauthorized

**Cause:** Missing permission or not authenticated

**Fix:**
```php
// Option 1: Assign permission
$user->givePermissionTo('roles.create');

// Option 2: Assign role with permissions
$user->assignRole('admin');

// Option 3: Make user super-admin
$user->assignRole('super-admin');
```

---

### Issue: Cache Not Clearing

**Cause:** Cache driver doesn't support tags

**Fix:**
```bash
# Clear manually
php artisan permission:cache-reset
php artisan cache:clear

# Or use Redis (supports tags)
# .env
CACHE_STORE=redis
```

---

### Issue: Team Scoping Not Working

**Cause:** Tenant context not set

**Fix:**
```php
// Set tenant context before querying
app()->instance('permission.team_id', auth()->user()->team_id);

// Then query
$roles = Role::all(); // Now scoped
```

---

### Issue: Migration Conflicts

**Cause:** Spatie migrations not run first

**Fix:**
```bash
# Run Spatie migrations first
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate

# Then run package migrations
php artisan migrate --path=vendor/enadstack/laravel-roles/database/migrations
```

---

## 📊 Performance Tips

### 1. Enable Redis Cache
```env
CACHE_STORE=redis
```

### 2. Eager Load Relationships
```php
// Instead of
$roles = Role::all();
foreach ($roles as $role) {
    echo $role->permissions->count(); // N+1 query
}

// Do this
$roles = Role::with('permissions')->get();
foreach ($roles as $role) {
    echo $role->permissions->count(); // Single query
}
```

### 3. Use Cached Methods
```php
use Enadstack\LaravelRoles\Services\PermissionService;

$permissionService = app(PermissionService::class);

// These are cached
$groupedPermissions = $permissionService->getGroupedPermissions();
$matrix = $permissionService->getPermissionMatrix();
```

---

## 🔍 Useful Commands

```bash
# Install package
php artisan roles:install

# Sync permissions from config
php artisan roles:sync

# Sync and prune
php artisan roles:sync --prune

# Clear permission cache
php artisan permission:cache-reset

# Clear all caches
php artisan cache:clear

# Run tests
composer test

# Or
vendor/bin/pest
```

---

## 📚 Documentation Links

- **Full Analysis:** `COMPLETE_PACKAGE_ANALYSIS.md`
- **Complete README:** `NEW_COMPLETE_README.md`
- **Summary:** `PACKAGE_DOCUMENTATION_SUMMARY.md`
- **Spatie Docs:** https://spatie.be/docs/laravel-permission

---

## 🚦 Status Checks

### Is Everything Working?

```php
// Test 1: Can I create a role?
$role = \Enadstack\LaravelRoles\Models\Role::create(['name' => 'test-role']);
echo $role ? '✅ Roles working' : '❌ Roles not working';

// Test 2: Can I create a permission?
$perm = \Enadstack\LaravelRoles\Models\Permission::create(['name' => 'test.permission']);
echo $perm ? '✅ Permissions working' : '❌ Permissions not working';

// Test 3: Can I assign permission to role?
$role->givePermissionTo($perm);
echo $role->hasPermissionTo('test.permission') ? '✅ Assignment working' : '❌ Assignment not working';

// Test 4: Can user have roles?
$user = auth()->user();
$user->assignRole($role);
echo $user->hasRole('test-role') ? '✅ User roles working' : '❌ User roles not working';

// Clean up
$role->forceDelete();
$perm->forceDelete();
```

---

## 🎯 One-Minute Setup for New Project

```bash
# 1. Install
composer require enadstack/laravel-roles

# 2. Run installer (follow prompts)
php artisan roles:install

# 3. Configure (optional)
nano config/roles.php

# 4. Seed (optional)
php artisan roles:sync

# 5. Test
php artisan tinker
>>> $role = \Enadstack\LaravelRoles\Models\Role::create(['name' => 'admin'])
>>> $perm = \Enadstack\LaravelRoles\Models\Permission::create(['name' => 'users.manage'])
>>> $role->givePermissionTo($perm)
>>> auth()->user()->assignRole($role)
>>> auth()->user()->can('users.manage') // true

# ✅ Done!
```

---

## 💡 Pro Tips

1. **Use Config-Driven Seeding** for CI/CD consistency
2. **Enable Redis Cache** for better performance
3. **Implement Audit Logging** for compliance
4. **Use Service Layer** for complex business logic
5. **Add Rate Limiting** to bulk operations in production
6. **Test in Staging** before deploying permission changes
7. **Never Delete** `super-admin` role (package protects it)
8. **Use Team-Scoped** mode for multi-tenant SaaS
9. **Cache Permission Matrix** - it's expensive to compute
10. **Listen to Events** for custom logic (audit logs, notifications)

---

## 🌟 Quick Wins

**5-Minute Improvements:**

1. Add policy registration in service provider
2. Add rate limiting to routes
3. Enable audit logging via events
4. Add database indexes
5. Configure Redis cache

---

**Need help?** Check the full documentation in `NEW_COMPLETE_README.md`

**Found a bug?** See `COMPLETE_PACKAGE_ANALYSIS.md` Section 3

**Want to contribute?** Review `COMPLETE_PACKAGE_ANALYSIS.md` Section 7

---

**Package Score: 82/100 (B+)** ✅ Production-Ready

**Made with ❤️ by Enad Abuzaid**


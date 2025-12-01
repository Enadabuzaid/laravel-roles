# Multi-Tenancy Usage Guide

**Package**: Laravel Roles (enadstack/laravel-roles)  
**Date**: December 1, 2025

---

## Table of Contents

1. [Overview](#overview)
2. [Installation](#installation)
3. [Mode 1: Single (No Tenancy)](#mode-1-single-no-tenancy)
4. [Mode 2: Team Scoped (Same Database)](#mode-2-team-scoped-same-database)
5. [Mode 3: Multi Database (Stancl/Tenancy)](#mode-3-multi-database-stancltenancy)
6. [API Examples](#api-examples)
7. [Troubleshooting](#troubleshooting)

---

## Overview

This package supports three multi-tenancy modes:

| Mode | Database | Use Case |
|------|----------|----------|
| **single** | One DB | No multi-tenancy |
| **team_scoped** | One DB with tenant FK | SaaS with shared database |
| **multi_database** | Separate DB per tenant | Enterprise multi-tenancy |

---

## Installation

```bash
composer require enadstack/laravel-roles

php artisan roles:install
```

The installer will guide you through:
1. Language/i18n setup
2. Tenancy mode selection
3. Initial data seeding

---

## Mode 1: Single (No Tenancy)

### Configuration

```php
// config/roles.php
'tenancy' => [
    'mode' => 'single',
    'team_foreign_key' => 'team_id',
    'provider' => null,
],
```

### Usage

No special setup required. Use normally:

```php
// Create roles
$role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

// Assign to user
$user->assignRole('editor');

// Check permissions
if ($user->can('posts.create')) {
    // ...
}
```

---

## Mode 2: Team Scoped (Same Database)

### Overview

- One database for all tenants
- Roles and permissions have a tenant FK column
- Global records (NULL tenant) are shared across tenants
- Tenant-specific records override globals

### Configuration

#### Step 1: Run Installer

```bash
php artisan roles:install
```

Select **"Same DB, scope by tenant column"** and choose your FK name:
- `team_id` (default)
- `tenant_id`
- `provider_id`
- Custom name

#### Step 2: Configure

```php
// config/roles.php
'tenancy' => [
    'mode' => 'team_scoped',
    'team_foreign_key' => 'team_id', // or tenant_id, provider_id
    'provider' => null,
],
```

```php
// config/permission.php
'teams' => true,
'team_foreign_key' => 'team_id', // Must match roles.php
```

#### Step 3: Run Migrations

```bash
php artisan migrate
```

This adds:
- `team_id` column to `roles` and `permissions` tables
- Composite unique index: `[name, guard_name, team_id]`

### User Model Setup

Ensure your User model has the tenant identifier:

```php
// app/Models/User.php
class User extends Authenticatable
{
    use HasRoles;

    // Option 1: team_id column
    protected $fillable = ['name', 'email', 'password', 'team_id'];

    // Option 2: Relationship
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    // Option 3: provider_id
    protected $fillable = ['name', 'email', 'password', 'provider_id'];
}
```

### Middleware Setup

Register the middleware to set tenant context per request:

#### Laravel 11+ (bootstrap/app.php)

```php
use Enadstack\LaravelRoles\Http\Middleware\SetPermissionTeamId;

->withMiddleware(function (Middleware $middleware) {
    $middleware->group('api', [
        'auth:sanctum',
        SetPermissionTeamId::class, // Add this
    ]);
    
    $middleware->group('web', [
        'auth',
        SetPermissionTeamId::class, // Add this
    ]);
})
```

#### Laravel 10 (app/Http/Kernel.php)

```php
protected $middlewareGroups = [
    'web' => [
        // ...
        \Enadstack\LaravelRoles\Http\Middleware\SetPermissionTeamId::class,
    ],
    
    'api' => [
        // ...
        \Enadstack\LaravelRoles\Http\Middleware\SetPermissionTeamId::class,
    ],
];
```

### How It Works

The middleware automatically detects tenant ID from:

1. **User property** (checked in order):
   - `$user->team_id`
   - `$user->tenant_id`
   - `$user->provider_id`

2. **HTTP Header**:
   ```
   X-Tenant-Id: 123
   ```

3. **Query Parameter**:
   ```
   ?tenant_id=123
   ```

It then sets: `app()->instance('permission.team_id', $tenantId)`

### Creating Global vs Tenant-Specific Records

#### Create Global Role (Shared Across All Tenants)

```php
// Temporarily unset tenant context
$currentTenant = app('permission.team_id');
app()->forgetInstance('permission.team_id');

$globalRole = Role::create([
    'name' => 'super-admin',
    'guard_name' => 'web',
    // team_id will be NULL
]);

// Restore tenant context
if ($currentTenant) {
    app()->instance('permission.team_id', $currentTenant);
}
```

#### Create Tenant-Specific Role

```php
// With middleware, tenant context is auto-set
$tenantRole = Role::create([
    'name' => 'content-editor',
    'guard_name' => 'web',
    // team_id will be auto-set to current tenant
]);
```

### Querying

#### Standard Queries (Scoped to Current Tenant + Globals)

```php
// Returns: global roles + current tenant's roles
$roles = Role::all();

// Returns: global 'editor' role OR tenant's 'editor' role
// Tenant-specific takes priority
$editor = Role::findByName('editor');
```

#### Query All Tenants (Super-Admin Only)

```php
// Bypass tenant scope
$allRoles = Role::forAllTenants()->get();
```

#### Query Only Tenant-Specific (Exclude Globals)

```php
// Only this tenant's custom roles
$tenantRoles = Role::onlyTenantSpecific()->get();
```

#### Query Only Global Records

```php
// Only shared/global roles
$globalRoles = Role::onlyGlobal()->get();
```

#### Query Specific Tenant (Cross-Tenant Query)

```php
// Query a different tenant's roles
$otherTenantRoles = Role::forTenant(456)->get();
```

### Record Helper Methods

```php
$role = Role::find(1);

// Check if role is global (NULL team_id)
if ($role->isGlobal()) {
    // This role is shared across all tenants
}

// Check if role belongs to specific tenant
if ($role->belongsToTenant(123)) {
    // This role belongs to tenant 123
}

// Check if role belongs to current tenant
if ($role->belongsToCurrentTenant()) {
    // This role belongs to the current request's tenant
}
```

### Command Line Usage

#### Sync for Specific Tenant

```bash
# Seed roles/permissions for tenant ID 123
php artisan roles:sync --team-id=123
```

#### Sync Without Tenant (Global)

```bash
# Seeds global roles/permissions (team_id = NULL)
php artisan roles:sync
```

### Controller Example

```php
// app/Http/Controllers/RoleController.php
class RoleController extends Controller
{
    public function index(Request $request)
    {
        // Automatically scoped to current tenant (via middleware)
        $roles = Role::paginate(20);
        
        return response()->json($roles);
    }
    
    public function store(Request $request)
    {
        // team_id is auto-set to current tenant
        $role = Role::create($request->validated());
        
        return response()->json($role, 201);
    }
    
    public function adminIndex()
    {
        // Super-admin can see all tenants
        $allRoles = Role::forAllTenants()->paginate(20);
        
        return response()->json($allRoles);
    }
}
```

### API Authentication Example

```php
// routes/api.php
Route::middleware(['auth:sanctum', SetPermissionTeamId::class])->group(function() {
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
});

// Client request
curl -X GET https://api.example.com/roles \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant-Id: 123"  // Optional explicit tenant
```

### Database Structure

```sql
-- roles table
id | name | guard_name | team_id | created_at | updated_at | deleted_at
1  | super-admin | web | NULL | ...  | ... | NULL    -- Global role
2  | editor      | web | 123  | ...  | ... | NULL    -- Tenant 123's role
3  | editor      | web | 456  | ...  | ... | NULL    -- Tenant 456's role

-- When tenant 123 queries Role::where('name', 'editor')->first()
-- Returns: ID 2 (tenant-specific overrides global)

-- When no tenant context is set
-- Returns: ID 1 (global)
```

### Priority Rules

1. **Tenant-specific** records take priority over **global** records
2. If tenant has custom "editor" role, it overrides global "editor"
3. If tenant doesn't have custom role, global is used
4. This allows defaults with per-tenant customization

---

## Mode 3: Multi Database (Stancl/Tenancy)

### Overview

- Each tenant has their own database
- Roles and permissions are stored in tenant databases
- Complete data isolation

### Prerequisites

```bash
composer require stancl/tenancy
php artisan tenancy:install
```

### Configuration

#### Step 1: Run Installer

```bash
php artisan roles:install
```

Select **"Multi-database (each provider has its own DB)"** and select `stancl/tenancy`.

#### Step 2: Configure

```php
// config/roles.php
'tenancy' => [
    'mode' => 'multi_database',
    'provider' => 'stancl/tenancy',
],
```

#### Step 3: Move Migrations to Tenant Folder

```bash
# Create tenant migrations folder
mkdir -p database/migrations/tenant

# Move Spatie Permission migrations
cp vendor/spatie/laravel-permission/database/migrations/* database/migrations/tenant/

# Move package migrations
mv database/migrations/2025_10_13_112334_alter_roles* database/migrations/tenant/
mv database/migrations/2025_10_13_112335_alter_permissions* database/migrations/tenant/
```

#### Step 4: Configure Stancl Tenancy

```php
// config/tenancy.php
'migration_parameters' => [
    '--path' => [database_path('migrations/tenant')],
    '--realpath' => true,
],
```

#### Step 5: Run Migrations for All Tenants

```bash
# Migrate all tenant databases
php artisan tenants:artisan "migrate --force"
```

#### Step 6: Seed Each Tenant

```bash
# Seed all tenants
php artisan tenants:artisan "db:seed --class=\\Enadstack\\LaravelRoles\\Database\\Seeders\\RolesSeeder"
```

### Middleware Setup

```php
// bootstrap/app.php (Laravel 11+)
->withMiddleware(function (Middleware $middleware) {
    $middleware->group('api', [
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
        // or InitializeTenancyBySubdomain, InitializeTenancyByPath, etc.
        'auth:sanctum',
    ]);
})
```

### Usage

Once Stancl tenancy is initialized, all queries automatically use the tenant database:

```php
// Tenant context is set by Stancl middleware
// No need for team_id - separate database per tenant

$roles = Role::all(); // Queries current tenant's database

$role = Role::create(['name' => 'editor']); // Creates in tenant DB
```

### Per-Tenant Seeding

```php
// Seed a specific tenant
php artisan tenants:artisan "db:seed --class=\\Enadstack\\LaravelRoles\\Database\\Seeders\\RolesSeeder" --tenant=1
```

### Querying Central Database (Landlord)

```php
// If you need to query central database
tenancy()->central(function () {
    // Queries landlord database
    $tenants = Tenant::all();
});
```

---

## API Examples

### Team Scoped Mode

#### Create Role for Current Tenant

```http
POST /api/admin/acl/roles
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "content-editor",
  "guard_name": "web"
}
```

Response:
```json
{
  "data": {
    "id": 5,
    "name": "content-editor",
    "guard_name": "web",
    "team_id": 123,  // Auto-set from authenticated user
    "created_at": "2025-12-01T10:00:00Z"
  }
}
```

#### List Roles (Scoped to Current Tenant)

```http
GET /api/admin/acl/roles
Authorization: Bearer {token}
```

Response:
```json
{
  "data": [
    {
      "id": 1,
      "name": "super-admin",
      "team_id": null,  // Global role
      "created_at": "..."
    },
    {
      "id": 5,
      "name": "content-editor",
      "team_id": 123,   // Tenant-specific
      "created_at": "..."
    }
  ],
  "meta": { "current_page": 1, "total": 2 }
}
```

#### Assign Permission to Role

```http
POST /api/admin/acl/roles/5/permissions
Authorization: Bearer {token}
Content-Type: application/json

{
  "permissions": ["posts.create", "posts.update"]
}
```

### Multi Database Mode (Stancl)

Same API, but:
- Tenant identified by domain/subdomain
- Each tenant has isolated data
- No team_id in responses

```http
GET https://tenant1.example.com/api/admin/acl/roles
Authorization: Bearer {token}
```

Response:
```json
{
  "data": [
    {
      "id": 1,
      "name": "editor",
      "guard_name": "web",
      // No team_id - separate database
      "created_at": "..."
    }
  ]
}
```

---

## Troubleshooting

### Issue: Roles/Permissions Not Scoped to Tenant

**Problem**: All tenants see all roles

**Solution**:
1. Verify mode is set to `team_scoped` in `config/roles.php`
2. Check `SetPermissionTeamId` middleware is registered
3. Verify user has `team_id`, `tenant_id`, or `provider_id` property
4. Check middleware runs before controllers

**Debug**:
```php
// In your controller
dd(app('permission.team_id')); // Should show current tenant ID
```

### Issue: Cannot Create Role - Duplicate Entry

**Problem**: `Duplicate entry 'editor-web' for key 'roles_name_guard_name_unique'`

**Solution**:
The unique index needs to include `team_id`. Run migrations:
```bash
php artisan migrate
```

The migration will update the unique constraint to: `[name, guard_name, team_id]`

### Issue: HasTenantScope Not Working

**Problem**: Global scope not filtering queries

**Solution**:
1. Verify models use the trait:
   ```php
   use Enadstack\LaravelRoles\Traits\HasTenantScope;
   ```
2. Clear cache:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```
3. Check tenant context is set:
   ```php
   // Should return current tenant ID
   app('permission.team_id');
   ```

### Issue: Stancl/Tenancy Migrations Not Running

**Problem**: Tables don't exist in tenant databases

**Solution**:
1. Ensure migrations are in `database/migrations/tenant/`
2. Verify `tenancy.php` config points to tenant migrations
3. Run migrations:
   ```bash
   php artisan tenants:artisan "migrate --force"
   ```

### Issue: User Cannot See Global Roles

**Problem**: Tenant-only queries exclude global records

**Solution**:
Global roles are automatically included. If not visible:
1. Check `team_id` column has `NULL` for global roles
2. Verify `TenantScope` is working:
   ```php
   // Should include global + tenant
   $roles = Role::all();
   
   // Check raw query
   dd(Role::toSql());
   ```

---

## Best Practices

### 1. Use Global Roles for Common Roles

```php
// Create global roles during installation
Role::create([
    'name' => 'user',
    'team_id' => null,  // Global
]);

// Tenants can customize if needed
Role::create([
    'name' => 'user',
    'team_id' => 123,  // Override for tenant 123
]);
```

### 2. Protect Super-Admin Routes

```php
Route::middleware(['auth', 'can:super-admin'])->group(function() {
    // Super-admin can bypass tenant scope
    Route::get('/admin/all-roles', function() {
        return Role::forAllTenants()->get();
    });
});
```

### 3. Validate Tenant Ownership in Bulk Operations

```php
public function bulkDelete(Request $request)
{
    $ids = $request->input('ids');
    
    // Verify all roles belong to current tenant
    $roles = Role::whereIn('id', $ids)->get();
    
    foreach ($roles as $role) {
        if (!$role->isGlobal() && !$role->belongsToCurrentTenant()) {
            abort(403, 'Cannot delete role from another tenant');
        }
    }
    
    // Proceed with deletion
}
```

### 4. Use Transactions for Multi-Tenant Operations

```php
DB::transaction(function() use ($roleData, $permissions) {
    $role = Role::create($roleData);
    $role->syncPermissions($permissions);
});
```

### 5. Log Tenant Context for Debugging

```php
Log::info('Role created', [
    'role' => $role->name,
    'tenant_id' => app('permission.team_id'),
    'user_id' => auth()->id(),
]);
```

---

## Summary

| Feature | Team Scoped | Multi Database |
|---------|-------------|----------------|
| **Setup Complexity** | Low | Medium |
| **Data Isolation** | Partial (same DB) | Complete (separate DB) |
| **Global Roles** | ✅ Supported | ❌ N/A |
| **Performance** | High | Medium |
| **Backup** | Simple | Per-tenant |
| **Scaling** | Vertical | Horizontal |

Choose **Team Scoped** for:
- Cost-effective SaaS
- Moderate data isolation needs
- Shared global resources

Choose **Multi Database** for:
- Enterprise customers
- Complete data isolation
- Regulatory compliance needs
- Unlimited scaling

---

**Generated**: December 1, 2025  
**Package**: enadstack/laravel-roles v1.1.0+


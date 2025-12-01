# Multi-Tenancy Support Verification

**Package**: Laravel Roles (enadstack/laravel-roles)  
**Date**: December 1, 2025  
**Version**: v1.1.0+

---

## 🔍 Executive Summary

This package provides **PARTIAL multi-tenancy support** with three modes:

1. ✅ **Single** - No multi-tenancy (fully functional)
2. ⚠️ **Team Scoped** - Same DB with tenant FK (PARTIALLY implemented)
3. ⚠️ **Multi Database** - Separate DB per tenant (PARTIALLY supported)

---

## A. Global "provider_id" / Tenant Scope (Team Scoped Mode)

### Configuration

**Config Location**: `config/roles.php`

```php
'tenancy' => [
    'mode' => 'team_scoped',           // Enable team scoping
    'team_foreign_key' => 'team_id',   // Can be: team_id, tenant_id, provider_id
    'provider' => null,
],
```

**Spatie Permission Config**: `config/permission.php`

```php
'teams' => true,                       // Must be enabled
'team_foreign_key' => 'team_id',      // Must match roles.php
```

---

### ✅ What's IMPLEMENTED

#### 1. **Migration Support** ✅
- **File**: `database/migrations/2025_10_13_112334_alter_roles_add_i18n_tenant_softdeletes.php`
- **File**: `database/migrations/2025_10_13_112335_alter_permissions_add_i18n_group_tenant_softdeletes.php`

**What it does**:
- Adds tenant FK column to `roles` and `permissions` tables
- Creates composite unique index: `[name, guard_name, team_id]`
- Makes the FK nullable (supports global + tenant-specific records)
- Respects custom FK name from config

```php
// Migration excerpt
if (config('roles.tenancy.mode') === 'team_scoped') {
    $fk = config('permission.team_foreign_key', 'team_id');
    if (!Schema::hasColumn('roles', $fk)) {
        $table->unsignedBigInteger($fk)->nullable()->index()->after('guard_name');
    }
    $table->unique(array_filter(['name', 'guard_name', $fk]));
}
```

**Status**: ✅ **CORRECT**

---

#### 2. **Model Scope Logic** ⚠️ PARTIALLY CORRECT

**File**: `src/Models/Role.php`
**File**: `src/Models/Permission.php`

Both models override `findByName()` to respect tenant scope:

```php
public static function findByName(string $name, $guardName = null): self
{
    $guardName ??= config('auth.defaults.guard');
    
    $query = static::query()
        ->where('name', $name)
        ->where('guard_name', $guardName);
    
    // Prefer tenant-specific record if team_scoped is enabled
    if (config('roles.tenancy.mode') === 'team_scoped') {
        $tenantId = app('permission.team_id');
        $query->where(function ($q) use ($tenantId) {
            $q->whereNull(config('permission.team_foreign_key', 'team_id'))
                ->orWhere(config('permission.team_foreign_key', 'team_id'), $tenantId);
        })->orderByRaw('CASE WHEN '.
            config('permission.team_foreign_key', 'team_id').
            ' IS NULL THEN 1 ELSE 0 END'); // prefer tenant-specific
    }
    
    $role = $query->first();
    
    if (!$role) {
        throw RoleDoesNotExist::named($name, $guardName);
    }
    
    return $role;
}
```

**What it does**:
- Queries for both global (NULL tenant) and tenant-specific records
- Prioritizes tenant-specific over global
- Reads tenant ID from `app('permission.team_id')`

**Issues**: ⚠️
1. ❌ Only affects `findByName()` - other queries don't auto-scope
2. ❌ No global scope applied to all queries
3. ❌ Manual filtering needed in services/controllers

**Status**: ⚠️ **PARTIALLY CORRECT** - Works but incomplete

---

#### 3. **Middleware for Setting Tenant Context** ✅

**File**: `src/Http/Middleware/SetPermissionTeamId.php`

```php
public function handle(Request $request, Closure $next): Response
{
    // Only apply if tenancy mode is team_scoped
    if (config('roles.tenancy.mode') !== 'team_scoped') {
        return $next($request);
    }

    $user = $request->user();

    if ($user) {
        // Attempt to get team ID from various common property names
        $teamId = $user->team_id
            ?? $user->tenant_id
            ?? $user->provider_id  // ✅ SUPPORTS provider_id
            ?? null;

        // Or from request header
        if (!$teamId && $request->hasHeader('X-Tenant-Id')) {
            $teamId = $request->header('X-Tenant-Id');
        }

        // Or from request query parameter
        if (!$teamId && $request->has('tenant_id')) {
            $teamId = $request->input('tenant_id');
        }

        // Set the tenant context for Spatie Permission
        if ($teamId) {
            app()->instance('permission.team_id', $teamId);
        }
    }

    return $next($request);
}
```

**Features**:
- ✅ Checks `team_id`, `tenant_id`, `provider_id` on user
- ✅ Supports `X-Tenant-Id` header
- ✅ Supports `tenant_id` query parameter
- ✅ Only runs when `team_scoped` mode is enabled

**Usage**:
```php
// In routes/api.php or bootstrap/app.php
Route::middleware(['auth:sanctum', SetPermissionTeamId::class])->group(function() {
    // Your routes
});
```

**Status**: ✅ **CORRECT**

---

#### 4. **Bypass Logic** ❌ NOT IMPLEMENTED

**Issue**: No way to bypass tenant scope for super-admin queries

**Missing**:
- No `withoutGlobalScope()` available
- No admin bypass mechanism
- No way to query across all tenants

**Workaround**:
```php
// Manual bypass (not elegant)
app()->forgetInstance('permission.team_id');
$allRoles = Role::all();
app()->instance('permission.team_id', $originalTenantId);
```

**Status**: ❌ **NOT IMPLEMENTED**

---

#### 5. **API Controllers - Tenant Scope** ⚠️ PARTIALLY IMPLEMENTED

**Files**: 
- `src/Http/Controllers/RoleController.php`
- `src/Http/Controllers/PermissionController.php`

**Current Implementation**:
- Controllers rely on middleware to set `app('permission.team_id')`
- Models use this in `findByName()` only
- Other queries (list, stats, etc.) do NOT automatically filter by tenant

**Example Issue**:
```php
// RoleService.php - list() method
public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
{
    $query = Role::query(); // ❌ Does NOT filter by tenant automatically
    
    // ... filters ...
    
    return $query->paginate($perPage);
}
```

**What's Missing**:
1. ❌ No automatic tenant filtering in list queries
2. ❌ No tenant filtering in stats queries
3. ❌ No tenant filtering in recent queries
4. ❌ Bulk operations don't verify tenant ownership

**Status**: ⚠️ **NEEDS IMPROVEMENT**

---

#### 6. **Command Support** ✅

**File**: `src/Commands/SyncCommand.php`

```php
protected $signature = 'roles:sync
    {--team-id= : When team_scoped, run sync against a specific tenant/team id}
    ...
';

public function handle(): int
{
    $teamId = $this->option('team-id');
    $isTeamScoped = config('roles.tenancy.mode') === 'team_scoped';
    
    if ($isTeamScoped && $teamId !== null) {
        app()->instance('permission.team_id', $teamId);
        $this->components->info("Syncing under team_id={$teamId}");
    }
    
    // ... rest of sync logic
}
```

**Usage**:
```bash
php artisan roles:sync --team-id=123
```

**Status**: ✅ **CORRECT**

---

### ❌ What's MISSING for Team Scoped Mode

1. **Global Scope on Models**
   ```php
   // SHOULD EXIST but DOESN'T
   // src/Models/Scopes/TenantScope.php
   class TenantScope implements Scope
   {
       public function apply(Builder $builder, Model $model)
       {
           if (config('roles.tenancy.mode') === 'team_scoped') {
               $tenantId = app('permission.team_id');
               $fk = config('permission.team_foreign_key', 'team_id');
               
               $builder->where(function($q) use ($fk, $tenantId) {
                   $q->whereNull($fk)
                     ->orWhere($fk, $tenantId);
               });
           }
       }
   }
   ```

2. **Tenant Trait for Models**
   ```php
   // SHOULD EXIST but DOESN'T
   // src/Traits/HasTenantScope.php
   trait HasTenantScope
   {
       protected static function bootHasTenantScope()
       {
           static::addGlobalScope(new TenantScope());
       }
       
       public function scopeForAllTenants($query)
       {
           return $query->withoutGlobalScope(TenantScope::class);
       }
   }
   ```

3. **Automatic Tenant ID on Create**
   ```php
   // SHOULD EXIST in models but DOESN'T
   protected static function boot()
   {
       parent::boot();
       
       static::creating(function ($model) {
           if (config('roles.tenancy.mode') === 'team_scoped') {
               $fk = config('permission.team_foreign_key', 'team_id');
               if (!$model->$fk && app()->bound('permission.team_id')) {
                   $model->$fk = app('permission.team_id');
               }
           }
       });
   }
   ```

4. **Service Layer Tenant Filtering**
   - All list/query methods should respect tenant scope
   - Bulk operations should verify ownership

---

## B. Stancl Tenancy (stancl/tenancy) Support

### Configuration

```php
'tenancy' => [
    'mode' => 'multi_database',
    'provider' => 'stancl/tenancy',
],
```

---

### ✅ What's IMPLEMENTED

#### 1. **Config Recognition** ✅

The install command detects `stancl/tenancy`:

```php
// InstallCommand.php
$stanclClass = '\\Stancl\\Tenancy\\TenancyServiceProvider';
$provider = class_exists($stanclClass)
    ? 'stancl/tenancy'
    : select('Tenancy provider', ...);
```

**Status**: ✅ **CORRECT**

---

#### 2. **Installation Guidance** ✅

The install command provides instructions:

```php
if ($provider === 'stancl/tenancy') {
    $this->line('Move Spatie migrations to database/migrations/tenant and run:');
    $this->line('  php artisan tenants:artisan "migrate --force"');
}
```

**Status**: ✅ **CORRECT** but manual

---

### ❌ What's MISSING for Stancl Tenancy

#### 1. **Tenant-Aware Models** ❌

**Missing**: No `BelongsToTenant` trait usage

```php
// SHOULD BE in Role.php and Permission.php
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Role extends SpatieRole
{
    use SoftDeletes;
    use BelongsToTenant; // ❌ MISSING
}
```

**Impact**: Models won't automatically use tenant database connection

**Status**: ❌ **NOT IMPLEMENTED**

---

#### 2. **Tenant Migration Location** ⚠️

**Current**: Migrations are in `database/migrations/`  
**Should be**: `database/migrations/tenant/` for stancl/tenancy

**Workaround**: Manual move required after install

```bash
# Manual steps
mkdir -p database/migrations/tenant
mv vendor/spatie/laravel-permission/database/migrations/* database/migrations/tenant/
mv database/migrations/2025_10_13_112334_alter_roles* database/migrations/tenant/
mv database/migrations/2025_10_13_112335_alter_permissions* database/migrations/tenant/
```

**Status**: ⚠️ **MANUAL SETUP REQUIRED**

---

#### 3. **Tenant Database Connection** ❌

**Missing**: No automatic tenant DB connection switching

```php
// SHOULD EXIST in models
protected $connection = 'tenant'; // ❌ MISSING
```

**Impact**: Without `BelongsToTenant` or explicit connection, queries may hit wrong DB

**Status**: ❌ **NOT IMPLEMENTED**

---

#### 4. **Tenant Context Helpers** ❌

**Missing**: No usage of `tenant()` helper

```php
// EXAMPLE of what SHOULD exist but DOESN'T
if (tenancy()->initialized) {
    $currentTenant = tenant();
    // Do tenant-specific operations
}
```

**Status**: ❌ **NOT USED**

---

#### 5. **TenancyMiddleware Integration** ⚠️

**Current**: Package doesn't include or reference Stancl middleware

**Required by user**:
```php
// app/Http/Kernel.php or bootstrap/app.php
'api' => [
    \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
    // or InitializeTenancyBySubdomain, etc.
],
```

**Status**: ⚠️ **USER MUST CONFIGURE**

---

#### 6. **Seeder Compatibility** ⚠️

**File**: `database/seeders/RolesSeeder.php`

**Current**:
- Seeder works per-database
- Can be run via `tenants:artisan` command
- No tenant-specific logic

**Required**:
```bash
# User must run manually
php artisan tenants:artisan "db:seed --class=RolesSeeder"
```

**Status**: ⚠️ **WORKS BUT MANUAL**

---

#### 7. **Config Publishing** ⚠️

**Issue**: Package config `roles.php` is global, not tenant-specific

**Impact**: All tenants share same role/permission definitions from config

**Solution**: This is actually CORRECT for most use cases
- Config defines structure (permission groups, default roles)
- Each tenant has their own data in their database
- If tenants need different configs, handle at app level

**Status**: ✅ **ACCEPTABLE DESIGN**

---

### 📋 Required Configuration for Stancl/Tenancy

To make this package work with `stancl/tenancy`, users must:

#### Step 1: Install stancl/tenancy
```bash
composer require stancl/tenancy
php artisan tenancy:install
```

#### Step 2: Move Migrations
```bash
mkdir -p database/migrations/tenant

# Move Spatie Permission migrations
mv vendor/spatie/laravel-permission/database/migrations/* database/migrations/tenant/

# Move this package's migrations
mv database/migrations/2025_10_13_112334_alter_roles_add_i18n_tenant_softdeletes.php database/migrations/tenant/
mv database/migrations/2025_10_13_112335_alter_permissions_add_i18n_group_tenant_softdeletes.php database/migrations/tenant/
```

#### Step 3: Configure Tenancy
```php
// config/roles.php
'tenancy' => [
    'mode' => 'multi_database',
    'provider' => 'stancl/tenancy',
],
```

#### Step 4: Run Tenant Migrations
```bash
php artisan tenants:artisan "migrate --force"
```

#### Step 5: Seed Each Tenant
```bash
php artisan tenants:artisan "db:seed --class=\\Enadstack\\LaravelRoles\\Database\\Seeders\\RolesSeeder"
```

#### Step 6: Add Tenancy Middleware
```php
// bootstrap/app.php (Laravel 11+)
->withMiddleware(function (Middleware $middleware) {
    $middleware->group('api', [
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
        // ... other middleware
    ]);
})
```

---

## 📊 Compatibility Matrix

| Feature | Single Mode | Team Scoped | Multi Database (Stancl) |
|---------|-------------|-------------|------------------------|
| **Models** | ✅ | ⚠️ Partial | ❌ Needs BelongsToTenant |
| **Migrations** | ✅ | ✅ | ⚠️ Manual move |
| **Middleware** | ✅ | ✅ | ⚠️ User configures |
| **Commands** | ✅ | ✅ | ⚠️ Manual run |
| **API Controllers** | ✅ | ⚠️ No auto-filter | ✅ (via Stancl) |
| **Seeders** | ✅ | ✅ | ⚠️ Manual per tenant |
| **Global Scope** | N/A | ❌ Missing | N/A |
| **Bypass Logic** | N/A | ❌ Missing | ✅ (Stancl provides) |
| **Auto Tenant FK** | N/A | ❌ Missing | N/A |

---

## 🔧 Required Improvements

### Priority 1: Team Scoped Mode

1. **Add Global Scope** (Critical)
   - Create `TenantScope` class
   - Apply to Role and Permission models
   - Auto-filter all queries by tenant

2. **Add Tenant Trait** (Critical)
   - Auto-set tenant FK on create
   - Provide bypass methods
   - Clean API for cross-tenant queries

3. **Update Services** (Critical)
   - Ensure all queries respect tenant scope
   - Add tenant ownership verification for bulk ops

### Priority 2: Stancl/Tenancy Support

1. **Add BelongsToTenant Trait** (High)
   - Import from stancl/tenancy
   - Apply to models when multi_database mode

2. **Provide Tenant Migration Path** (Medium)
   - Auto-detect and suggest migration location
   - Or provide artisan command to move migrations

3. **Documentation** (High)
   - Detailed setup guide for Stancl
   - Examples for common scenarios

---

## ✅ Recommendations

### For Single Project (No Tenancy)
✅ **READY TO USE** - No changes needed

### For Team Scoped (Same DB)
⚠️ **NEEDS WORK**:
1. Implement global scope (see below)
2. Update services to respect scope
3. Add tenant FK auto-population
4. Test thoroughly

### For Multi-Database (Stancl/Tenancy)
⚠️ **WORKS WITH MANUAL SETUP**:
1. Follow configuration steps above
2. User must handle migration placement
3. User must configure Stancl middleware
4. Works well once set up

---

## 📝 Implementation Recommendations

### 1. Create Global Scope for Team Scoped Mode

**File**: `src/Models/Scopes/TenantScope.php`

```php
<?php

namespace Enadstack\LaravelRoles\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (config('roles.tenancy.mode') !== 'team_scoped') {
            return;
        }

        if (!app()->bound('permission.team_id')) {
            return; // No tenant context set
        }

        $tenantId = app('permission.team_id');
        $fk = config('permission.team_foreign_key', 'team_id');

        $builder->where(function ($query) use ($fk, $tenantId) {
            $query->whereNull($fk)
                ->orWhere($fk, $tenantId);
        });
    }
}
```

### 2. Create Tenant Trait

**File**: `src/Traits/HasTenantScope.php`

```php
<?php

namespace Enadstack\LaravelRoles\Traits;

use Enadstack\LaravelRoles\Models\Scopes\TenantScope;

trait HasTenantScope
{
    protected static function bootHasTenantScope()
    {
        // Apply global scope
        static::addGlobalScope(new TenantScope());

        // Auto-set tenant FK on create
        static::creating(function ($model) {
            if (config('roles.tenancy.mode') !== 'team_scoped') {
                return;
            }

            $fk = config('permission.team_foreign_key', 'team_id');

            if (!isset($model->$fk) && app()->bound('permission.team_id')) {
                $model->$fk = app('permission.team_id');
            }
        });
    }

    /**
     * Query across all tenants (bypass scope)
     */
    public function scopeForAllTenants($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Query only tenant-specific records (exclude global)
     */
    public function scopeOnlyTenantSpecific($query)
    {
        $fk = config('permission.team_foreign_key', 'team_id');
        return $query->withoutGlobalScope(TenantScope::class)
            ->whereNotNull($fk);
    }

    /**
     * Query only global records (no tenant)
     */
    public function scopeOnlyGlobal($query)
    {
        $fk = config('permission.team_foreign_key', 'team_id');
        return $query->withoutGlobalScope(TenantScope::class)
            ->whereNull($fk);
    }
}
```

### 3. Update Models

```php
// src/Models/Role.php
use Enadstack\LaravelRoles\Traits\HasTenantScope;

class Role extends SpatieRole
{
    use SoftDeletes;
    use HasTenantScope; // Add this

    // ... rest of code
}

// src/Models/Permission.php
class Permission extends SpatiePermission
{
    use SoftDeletes;
    use HasTenantScope; // Add this

    // ... rest of code
}
```

### 4. Add Stancl/Tenancy Support (Optional)

```php
// src/Models/Role.php
class Role extends SpatieRole
{
    use SoftDeletes;
    use HasTenantScope;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Use tenant connection if stancl/tenancy is enabled
        if (config('roles.tenancy.mode') === 'multi_database' 
            && config('roles.tenancy.provider') === 'stancl/tenancy') {
            
            if (class_exists('\\Stancl\\Tenancy\\Database\\Concerns\\BelongsToTenant')) {
                // Apply BelongsToTenant trait dynamically if available
                static::boot();
            }
        }
    }
}
```

---

## 🎯 Conclusion

### Current Status

| Mode | Status | Ready for Production? |
|------|--------|---------------------|
| **Single** | ✅ Fully Implemented | ✅ **YES** |
| **Team Scoped** | ⚠️ Partially Implemented | ⚠️ **WITH CAUTION** |
| **Multi Database (Stancl)** | ⚠️ Basic Support | ⚠️ **WITH MANUAL SETUP** |

### Recommended Actions

1. **Immediate**: Implement Global Scope for team_scoped mode
2. **Short-term**: Add tenant trait and auto-population
3. **Medium-term**: Add BelongsToTenant support for Stancl
4. **Long-term**: Full Stancl integration with auto-detection

### Current Verdict

✅ **Package works well for single-tenant applications**  
⚠️ **Team-scoped mode needs improvements for production use**  
⚠️ **Stancl/tenancy support is basic but functional with manual setup**

---

**Generated**: December 1, 2025  
**Reviewer**: GitHub Copilot  
**Package Version**: v1.1.0+


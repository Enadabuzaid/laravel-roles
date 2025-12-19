# Laravel Roles v2.0 - Complete Release Blueprint

**Package:** enadstack/laravel-roles  
**Target Version:** 2.0.0  
**Laravel:** 12.x | **PHP:** 8.2+ | **Stack:** Inertia.js (Vue 3) + shadcn-vue

---

## 🎯 RELEASE OBJECTIVES

Deliver a production-ready, reusable Roles & Permissions package that:
- ✅ Works in **any Laravel project** (single-tenant or multi-tenant)
- ✅ Supports **English/Arabic** with RTL
- ✅ Includes **admin UI** (Inertia + shadcn-vue)
- ✅ Has **100% test coverage** with automated CI
- ✅ Provides **complete documentation**

---

## 📋 PHASE-BY-PHASE IMPLEMENTATION PLAN

### **PHASE 1: Configuration & Architecture** (Days 1-2)

#### 1.1 Enhanced Configuration Design
**File:** `config/roles.php`

```php
return [
    // Multi-tenancy (ENHANCED)
    'tenancy' => [
        'mode' => env('ROLES_TENANCY_MODE', 'single'), // single|team_scoped|multi_database
        'team_foreign_key' => env('ROLES_TEAM_KEY', 'team_id'),
        'provider' => env('ROLES_TENANCY_PROVIDER'), // stancl/tenancy|spatie/laravel-multitenancy
        'scope_column' => env('ROLES_SCOPE_COLUMN', 'team_id'), // team_id|tenant_id|organization_id
    ],
    
    // i18n (ENHANCED)
    'i18n' => [
        'enabled' => env('ROLES_I18N_ENABLED', false),
        'locales' => ['en', 'ar'],
        'default' => env('ROLES_DEFAULT_LOCALE', 'en'),
        'fallback' => 'en',
        'rtl_locales' => ['ar'],
    ],
    
    // Guards (ENHANCED)
    'guards' => [
        'default' => env('ROLES_GUARD', 'web'),
        'allowed' => ['web', 'api', 'sanctum'], // Validation list
    ],
    
    // UI (NEW)
    'ui' => [
        'enabled' => env('ROLES_UI_ENABLED', true),
        'prefix' => env('ROLES_UI_PREFIX', 'admin/acl'),
        'middleware' => ['web', 'auth'],
        'layout' => env('ROLES_UI_LAYOUT', 'app'), // Your app layout component
    ],
    
    // API (EXISTING - Enhanced)
    'routes' => [
        'prefix' => env('ROLES_API_PREFIX', 'api/admin/acl'),
        'middleware' => ['api', 'auth:sanctum'],
        'guard' => env('ROLES_GUARD', 'web'),
        'expose_me' => true,
    ],
    
    // Cache (EXISTING)
    'cache' => [
        'enabled' => true,
        'ttl' => 300,
        'tags' => ['roles', 'permissions'],
    ],
    
    // Validation (NEW)
    'validation' => [
        'role_name_regex' => '/^[a-z0-9-]+$/',
        'permission_name_regex' => '/^[a-z0-9.-]+$/',
        'max_name_length' => 50,
    ],
];
```

**Behavior Matrix:**

| Mode | Tenancy | Locale | Guard | Translation Files | Scope Strategy |
|------|---------|--------|-------|-------------------|----------------|
| single | off | en | web | optional | none |
| single | off | ar | web | required | none |
| team_scoped | on | en | web | optional | team_id FK |
| team_scoped | on | ar | api | required | team_id FK |
| multi_database | on | en | api | optional | separate DB |

---

### **PHASE 2: Backend API Enhancement** (Days 3-5)

#### 2.1 API Endpoint Inventory

**ROLES CRUD:**
| Method | Endpoint | Controller@Method | Request | Response | Status Codes |
|--------|----------|-------------------|---------|----------|--------------|
| GET | `/api/admin/acl/roles` | RoleController@index | - | RoleCollection | 200, 401, 403 |
| POST | `/api/admin/acl/roles` | RoleController@store | RoleStoreRequest | RoleResource | 201, 422, 401, 403 |
| GET | `/api/admin/acl/roles/{id}` | RoleController@show | - | RoleResource | 200, 404, 401, 403 |
| PUT | `/api/admin/acl/roles/{id}` | RoleController@update | RoleUpdateRequest | RoleResource | 200, 422, 404, 401, 403 |
| DELETE | `/api/admin/acl/roles/{id}` | RoleController@destroy | - | - | 204, 404, 401, 403 |
| POST | `/api/admin/acl/roles/{id}/restore` | RoleController@restore | - | RoleResource | 200, 404, 401, 403 |
| DELETE | `/api/admin/acl/roles/{id}/force` | RoleController@forceDelete | - | - | 204, 404, 401, 403 |
| POST | `/api/admin/acl/roles/bulk-delete` | RoleController@bulkDelete | BulkOperationRequest | - | 204, 422, 401, 403 |
| POST | `/api/admin/acl/roles/bulk-restore` | RoleController@bulkRestore | BulkOperationRequest | - | 200, 422, 401, 403 |

**PERMISSIONS CRUD:**
| Method | Endpoint | Controller@Method | Request | Response | Status Codes |
|--------|----------|-------------------|---------|----------|--------------|
| GET | `/api/admin/acl/permissions` | PermissionController@index | - | PermissionCollection | 200, 401, 403 |
| POST | `/api/admin/acl/permissions` | PermissionController@store | PermissionStoreRequest | PermissionResource | 201, 422, 401, 403 |
| GET | `/api/admin/acl/permissions/{id}` | PermissionController@show | - | PermissionResource | 200, 404, 401, 403 |
| PUT | `/api/admin/acl/permissions/{id}` | PermissionController@update | PermissionUpdateRequest | PermissionResource | 200, 422, 404, 401, 403 |
| DELETE | `/api/admin/acl/permissions/{id}` | PermissionController@destroy | - | - | 204, 404, 401, 403 |

**PERMISSION MATRIX:**
| Method | Endpoint | Controller@Method | Request | Response | Status Codes |
|--------|----------|-------------------|---------|----------|--------------|
| GET | `/api/admin/acl/permissions-matrix` | PermissionController@matrix | - | PermissionMatrixResource | 200, 401, 403 |
| POST | `/api/admin/acl/permissions-matrix/sync` | PermissionController@syncMatrix | SyncMatrixRequest | - | 200, 422, 401, 403 |

**USER-ROLE ASSIGNMENT:**
| Method | Endpoint | Controller@Method | Request | Response | Status Codes |
|--------|----------|-------------------|---------|----------|--------------|
| GET | `/api/admin/acl/users/{id}/roles` | UserRoleController@index | - | RoleCollection | 200, 404, 401, 403 |
| POST | `/api/admin/acl/users/{id}/roles/assign` | UserRoleController@assign | AssignRolesRequest | - | 200, 422, 404, 401, 403 |
| POST | `/api/admin/acl/users/{id}/roles/sync` | UserRoleController@sync | SyncRolesRequest | - | 200, 422, 404, 401, 403 |
| DELETE | `/api/admin/acl/users/{id}/roles/{roleId}` | UserRoleController@revoke | - | - | 204, 404, 401, 403 |

#### 2.2 JSON Response Schema (Standard)

**Success Response:**
```json
{
  "data": { /* resource */ },
  "meta": {
    "timestamp": "2025-12-19T12:00:00Z",
    "locale": "en"
  }
}
```

**Collection Response:**
```json
{
  "data": [ /* resources */ ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 15,
    "to": 15,
    "total": 67,
    "timestamp": "2025-12-19T12:00:00Z"
  }
}
```

**Error Response:**
```json
{
  "message": "Validation failed",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

---

### **PHASE 3: Backend Architecture** (Days 6-8)

#### 3.1 File Structure & Responsibilities

```
src/
├── Commands/
│   ├── InstallCommand.php          # Interactive installer
│   └── SyncPermissionsCommand.php  # Sync from config
│
├── Contracts/
│   ├── TenancyAdapterInterface.php # NEW: Tenancy abstraction
│   └── RoleServiceInterface.php    # NEW: Service contract
│
├── Events/
│   ├── RoleCreated.php
│   ├── RoleUpdated.php
│   ├── RoleDeleted.php
│   ├── PermissionCreated.php
│   ├── PermissionUpdated.php
│   ├── PermissionsAssignedToRole.php
│   └── PermissionMatrixSynced.php  # NEW
│
├── Exceptions/
│   ├── RoleNotFoundException.php
│   ├── PermissionNotFoundException.php
│   ├── InvalidTenancyModeException.php # NEW
│   └── UnauthorizedException.php   # NEW
│
├── Http/
│   ├── Controllers/
│   │   ├── RoleController.php
│   │   ├── PermissionController.php
│   │   ├── UserRoleController.php  # NEW
│   │   ├── SelfAclController.php
│   │   └── Inertia/                # NEW: Inertia controllers
│   │       ├── RolePageController.php
│   │       ├── PermissionPageController.php
│   │       └── MatrixPageController.php
│   │
│   ├── Middleware/
│   │   ├── SetPermissionTeamId.php
│   │   ├── CheckRolePermission.php # NEW
│   │   └── LocaleMiddleware.php    # NEW
│   │
│   ├── Requests/
│   │   ├── RoleStoreRequest.php
│   │   ├── RoleUpdateRequest.php
│   │   ├── PermissionStoreRequest.php
│   │   ├── PermissionUpdateRequest.php
│   │   ├── AssignPermissionsRequest.php
│   │   ├── BulkOperationRequest.php
│   │   ├── SyncMatrixRequest.php   # NEW
│   │   ├── AssignRolesRequest.php  # NEW
│   │   └── SyncRolesRequest.php    # NEW
│   │
│   └── Resources/
│       ├── RoleResource.php
│       ├── PermissionResource.php
│       ├── PermissionMatrixResource.php
│       └── UserRoleResource.php    # NEW
│
├── Listeners/
│   └── ClearPermissionCache.php
│
├── Models/
│   └── (Uses Spatie models)
│
├── Policies/
│   ├── RolePolicy.php
│   └── PermissionPolicy.php
│
├── Providers/
│   └── RolesServiceProvider.php
│
├── Repositories/                    # NEW: Repository pattern
│   ├── RoleRepository.php
│   └── PermissionRepository.php
│
├── Services/
│   ├── RoleService.php
│   ├── PermissionService.php
│   ├── TenancyService.php          # NEW
│   └── CacheService.php            # NEW
│
├── Support/                         # NEW: Helpers
│   ├── TenancyAdapters/
│   │   ├── StanclTenancyAdapter.php
│   │   ├── SpatieTenancyAdapter.php
│   │   └── NullTenancyAdapter.php
│   └── Helpers.php
│
└── Traits/
    └── HasTranslatableAttributes.php
```

---

### **PHASE 4: Frontend Admin UI** (Days 9-14)

#### 4.1 Inertia Routes

**File:** `routes/web.php` (published)

```php
use Enadstack\LaravelRoles\Http\Controllers\Inertia\RolePageController;
use Enadstack\LaravelRoles\Http\Controllers\Inertia\PermissionPageController;
use Enadstack\LaravelRoles\Http\Controllers\Inertia\MatrixPageController;

Route::prefix(config('roles.ui.prefix'))
    ->middleware(config('roles.ui.middleware'))
    ->group(function () {
        // Roles
        Route::get('/roles', [RolePageController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RolePageController::class, 'create'])->name('roles.create');
        Route::get('/roles/{role}', [RolePageController::class, 'show'])->name('roles.show');
        Route::get('/roles/{role}/edit', [RolePageController::class, 'edit'])->name('roles.edit');
        
        // Permissions
        Route::get('/permissions', [PermissionPageController::class, 'index'])->name('permissions.index');
        Route::get('/permissions/create', [PermissionPageController::class, 'create'])->name('permissions.create');
        Route::get('/permissions/{permission}', [PermissionPageController::class, 'show'])->name('permissions.show');
        Route::get('/permissions/{permission}/edit', [PermissionPageController::class, 'edit'])->name('permissions.edit');
        
        // Matrix
        Route::get('/matrix', [MatrixPageController::class, 'index'])->name('matrix.index');
    });
```

#### 4.2 Vue Pages Map

```
resources/js/Pages/Roles/
├── Index.vue           # List all roles (table + filters)
├── Create.vue          # Create new role form
├── Edit.vue            # Edit role form
└── Show.vue            # View role details + permissions

resources/js/Pages/Permissions/
├── Index.vue           # List all permissions (table + filters)
├── Create.vue          # Create permission form
├── Edit.vue            # Edit permission form
└── Show.vue            # View permission details

resources/js/Pages/Matrix/
└── Index.vue           # Permission matrix (roles × permissions grid)
```

#### 4.3 shadcn-vue Components Checklist

**Required Components:**
- [ ] `Button` - Primary actions
- [ ] `Input` - Text fields
- [ ] `Label` - Form labels
- [ ] `Table` - Data tables
- [ ] `Card` - Content containers
- [ ] `Badge` - Status indicators
- [ ] `Dialog` - Modals/confirmations
- [ ] `Select` - Dropdowns
- [ ] `Checkbox` - Multi-select
- [ ] `Switch` - Toggle states
- [ ] `Pagination` - Table pagination
- [ ] `Skeleton` - Loading states
- [ ] `Toast` - Notifications
- [ ] `DropdownMenu` - Action menus
- [ ] `Tabs` - Tab navigation
- [ ] `Alert` - Info/warning messages
- [ ] `Separator` - Visual dividers
- [ ] `ScrollArea` - Scrollable content

**Installation:**
```bash
npx shadcn-vue@latest init
npx shadcn-vue@latest add button input label table card badge dialog select checkbox switch pagination skeleton toast dropdown-menu tabs alert separator scroll-area
```

#### 4.4 Page Templates (Standard Structure)

**Template: Index Page**
```vue
<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import PageHeader from '@/Components/Roles/PageHeader.vue';
import DataTable from '@/Components/Roles/DataTable.vue';
import Filters from '@/Components/Roles/Filters.vue';
import Pagination from '@/Components/ui/pagination';

const props = defineProps<{
  roles: PaginatedResponse<Role>;
  filters: FilterState;
}>();

const viewMode = ref<'grid' | 'list'>('list');

const handleFilter = (filters: FilterState) => {
  router.get(route('roles.index'), filters, { preserveState: true });
};

const handleDelete = (id: number) => {
  if (confirm(t('roles.confirm_delete'))) {
    router.delete(route('roles.destroy', id));
  }
};
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      :title="$t('roles.title')"
      :description="$t('roles.description')"
      :create-route="route('roles.create')"
    />
    
    <Filters
      v-model="filters"
      @update:modelValue="handleFilter"
    />
    
    <DataTable
      :data="roles.data"
      :view-mode="viewMode"
      @delete="handleDelete"
    />
    
    <Pagination
      :meta="roles.meta"
      :links="roles.links"
    />
  </div>
</template>
```

#### 4.5 i18n Keys (EN/AR)

**File:** `resources/lang/en/roles.json`
```json
{
  "roles": {
    "title": "Roles",
    "description": "Manage user roles and permissions",
    "create": "Create Role",
    "edit": "Edit Role",
    "delete": "Delete Role",
    "confirm_delete": "Are you sure you want to delete this role?",
    "name": "Name",
    "label": "Label",
    "description": "Description",
    "permissions": "Permissions",
    "users_count": "Users",
    "created_at": "Created At",
    "actions": "Actions",
    "search_placeholder": "Search roles...",
    "no_results": "No roles found",
    "success_created": "Role created successfully",
    "success_updated": "Role updated successfully",
    "success_deleted": "Role deleted successfully"
  },
  "permissions": {
    "title": "Permissions",
    "description": "Manage system permissions",
    "create": "Create Permission",
    "name": "Name",
    "group": "Group",
    "label": "Label",
    "description": "Description"
  },
  "matrix": {
    "title": "Permission Matrix",
    "description": "Manage role-permission assignments",
    "save": "Save Changes",
    "success_saved": "Permissions updated successfully"
  }
}
```

**File:** `resources/lang/ar/roles.json`
```json
{
  "roles": {
    "title": "الأدوار",
    "description": "إدارة أدوار المستخدمين والصلاحيات",
    "create": "إنشاء دور",
    "edit": "تعديل الدور",
    "delete": "حذف الدور",
    "confirm_delete": "هل أنت متأكد من حذف هذا الدور؟",
    "name": "الاسم",
    "label": "التسمية",
    "description": "الوصف",
    "permissions": "الصلاحيات",
    "users_count": "المستخدمين",
    "created_at": "تاريخ الإنشاء",
    "actions": "الإجراءات",
    "search_placeholder": "البحث عن الأدوار...",
    "no_results": "لا توجد أدوار",
    "success_created": "تم إنشاء الدور بنجاح",
    "success_updated": "تم تحديث الدور بنجاح",
    "success_deleted": "تم حذف الدور بنجاح"
  }
}
```

**Fallback Strategy:**
1. Check current locale translation
2. Fall back to default locale (en)
3. Fall back to key name

---

### **PHASE 5: Testing Strategy** (Days 15-18)

#### 5.1 Orchestra Testbench Setup

**File:** `tests/TestCase.php`
```php
<?php

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Enadstack\LaravelRoles\Providers\RolesServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            RolesServiceProvider::class,
        ];
    }
    
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('roles.tenancy.mode', 'single');
        $app['config']->set('roles.i18n.enabled', true);
    }
    
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }
}
```

#### 5.2 Test Matrix

| Test Category | Tenancy Mode | Locale | Guard | Test Count | Priority |
|---------------|--------------|--------|-------|------------|----------|
| Role CRUD | single | en | web | 8 | P0 |
| Role CRUD | team_scoped | en | web | 8 | P0 |
| Permission CRUD | single | en | web | 6 | P0 |
| Permission Matrix | single | en | web | 4 | P0 |
| User-Role Assignment | single | en | web | 6 | P0 |
| Localization | single | ar | web | 4 | P1 |
| API Guard | single | en | api | 4 | P1 |
| Tenancy Switching | team_scoped | en | web | 6 | P1 |
| Cache Invalidation | single | en | web | 4 | P1 |
| Policy Authorization | single | en | web | 6 | P1 |

**Total Tests:** ~56

#### 5.3 Sample Feature Tests

**File:** `tests/Feature/RoleCrudTest.php`
```php
<?php

use function Pest\Laravel\{postJson, getJson, putJson, deleteJson};

it('creates a role with valid data', function () {
    $response = postJson('/api/admin/acl/roles', [
        'name' => 'editor',
        'label' => ['en' => 'Editor'],
        'description' => ['en' => 'Content editor'],
    ]);
    
    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'name', 'label', 'created_at']]);
    
    $this->assertDatabaseHas('roles', ['name' => 'editor']);
});

it('returns 422 for invalid role name', function () {
    $response = postJson('/api/admin/acl/roles', [
        'name' => 'Invalid Name!', // Invalid characters
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('clears cache after role creation', function () {
    Cache::shouldReceive('tags')->with(['roles', 'permissions'])->once()->andReturnSelf();
    Cache::shouldReceive('flush')->once();
    
    postJson('/api/admin/acl/roles', [
        'name' => 'test-role',
    ]);
});
```

**File:** `tests/Feature/TenancySwitchingTest.php`
```php
<?php

use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['roles.tenancy.mode' => 'team_scoped']);
});

it('scopes roles to team context', function () {
    setPermissionsTeamId(1);
    $role1 = Role::create(['name' => 'admin']);
    
    setPermissionsTeamId(2);
    $role2 = Role::create(['name' => 'admin']);
    
    expect($role1->id)->not->toBe($role2->id);
    
    setPermissionsTeamId(1);
    expect(Role::count())->toBe(1);
});
```

#### 5.4 CI Configuration

**File:** `.github/workflows/tests.yml`
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: [8.2, 8.3]
        laravel: [12.x]
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite
          coverage: xdebug
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction
      
      - name: Run tests
        run: vendor/bin/pest --coverage --min=80
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
```

---

### **PHASE 6: Documentation** (Days 19-20)

#### 6.1 README Structure

```markdown
# Laravel Roles & Permissions v2.0

## Features
## Requirements
## Installation
  - Composer install
  - Run installer
  - Publish assets
## Configuration
  - Tenancy modes
  - i18n setup
  - Guards
## UI Setup (NEW)
  - Install shadcn-vue
  - Publish Vue components
  - Configure Inertia
## API Reference
  - Endpoints table
  - Request/response examples
## Usage Examples
  - Single tenant
  - Multi-tenant
  - Localization
## Tenancy Setup
  - Stancl/tenancy
  - Spatie/multitenancy
## Testing
  - Run tests
  - Write custom tests
## Troubleshooting
  - Common issues
  - Cache problems
## Upgrade Guide
  - From v1.x to v2.0
## Changelog
## Contributing
```

---

## 📁 COMPLETE FILE CHECKLIST

### Backend Files

- [ ] `config/roles.php` - Enhanced configuration
- [ ] `src/Contracts/TenancyAdapterInterface.php`
- [ ] `src/Contracts/RoleServiceInterface.php`
- [ ] `src/Support/TenancyAdapters/StanclTenancyAdapter.php`
- [ ] `src/Support/TenancyAdapters/SpatieTenancyAdapter.php`
- [ ] `src/Support/TenancyAdapters/NullTenancyAdapter.php`
- [ ] `src/Services/TenancyService.php`
- [ ] `src/Services/CacheService.php`
- [ ] `src/Repositories/RoleRepository.php`
- [ ] `src/Repositories/PermissionRepository.php`
- [ ] `src/Http/Controllers/UserRoleController.php`
- [ ] `src/Http/Controllers/Inertia/RolePageController.php`
- [ ] `src/Http/Controllers/Inertia/PermissionPageController.php`
- [ ] `src/Http/Controllers/Inertia/MatrixPageController.php`
- [ ] `src/Http/Requests/SyncMatrixRequest.php`
- [ ] `src/Http/Requests/AssignRolesRequest.php`
- [ ] `src/Http/Requests/SyncRolesRequest.php`
- [ ] `src/Http/Resources/UserRoleResource.php`
- [ ] `src/Http/Middleware/LocaleMiddleware.php`
- [ ] `src/Http/Middleware/CheckRolePermission.php`
- [ ] `src/Exceptions/InvalidTenancyModeException.php`
- [ ] `src/Exceptions/UnauthorizedException.php`
- [ ] `src/Events/PermissionMatrixSynced.php`
- [ ] `routes/web.php` - Inertia routes

### Frontend Files

- [ ] `resources/js/Pages/Roles/Index.vue`
- [ ] `resources/js/Pages/Roles/Create.vue`
- [ ] `resources/js/Pages/Roles/Edit.vue`
- [ ] `resources/js/Pages/Roles/Show.vue`
- [ ] `resources/js/Pages/Permissions/Index.vue`
- [ ] `resources/js/Pages/Permissions/Create.vue`
- [ ] `resources/js/Pages/Permissions/Edit.vue`
- [ ] `resources/js/Pages/Permissions/Show.vue`
- [ ] `resources/js/Pages/Matrix/Index.vue`
- [ ] `resources/js/Components/Roles/PageHeader.vue`
- [ ] `resources/js/Components/Roles/DataTable.vue`
- [ ] `resources/js/Components/Roles/Filters.vue`
- [ ] `resources/js/Components/Roles/RoleCard.vue`
- [ ] `resources/js/Components/Roles/RoleForm.vue`
- [ ] `resources/js/Components/Permissions/PermissionTable.vue`
- [ ] `resources/js/Components/Permissions/PermissionForm.vue`
- [ ] `resources/js/Components/Matrix/MatrixGrid.vue`
- [ ] `resources/js/Composables/useRoles.ts`
- [ ] `resources/js/Composables/usePermissions.ts`
- [ ] `resources/js/Types/roles.d.ts`
- [ ] `resources/lang/en/roles.json`
- [ ] `resources/lang/ar/roles.json`
- [ ] `resources/lang/en/permissions.json`
- [ ] `resources/lang/ar/permissions.json`

### Test Files

- [ ] `tests/Feature/RoleCrudTest.php`
- [ ] `tests/Feature/PermissionCrudTest.php`
- [ ] `tests/Feature/PermissionMatrixTest.php`
- [ ] `tests/Feature/UserRoleAssignmentTest.php`
- [ ] `tests/Feature/TenancySwitchingTest.php`
- [ ] `tests/Feature/LocalizationTest.php`
- [ ] `tests/Feature/CacheInvalidationTest.php`
- [ ] `tests/Feature/PolicyAuthorizationTest.php`
- [ ] `tests/Feature/ApiGuardTest.php`
- [ ] `tests/Unit/TenancyAdapterTest.php`
- [ ] `tests/Unit/CacheServiceTest.php`

### Documentation Files

- [ ] `README.md` - Complete guide
- [ ] `INSTALLATION.md` - Detailed installation
- [ ] `TENANCY_GUIDE.md` - Multi-tenancy setup
- [ ] `UI_GUIDE.md` - Frontend setup
- [ ] `API_REFERENCE.md` - API documentation
- [ ] `UPGRADE.md` - Upgrade from v1.x
- [ ] `CHANGELOG.md` - Version history
- [ ] `CONTRIBUTING.md` - Contribution guidelines

---

## ✅ ACCEPTANCE CRITERIA VERIFICATION

### API Consistency
- [ ] All endpoints return consistent JSON schema
- [ ] Status codes: 200/201/204 (success), 401 (unauthorized), 403 (forbidden), 404 (not found), 422 (validation), 500 (server error)
- [ ] Error responses include `message` and `errors` keys

### Tenancy Support
- [ ] Works with `tenancy=single`
- [ ] Works with `tenancy=team_scoped`
- [ ] Works with `tenancy=multi_database`
- [ ] Adapter layer supports stancl/tenancy
- [ ] Adapter layer supports spatie/laravel-multitenancy

### Localization
- [ ] Works with `locale=en`
- [ ] Works with `locale=ar`
- [ ] RTL support for Arabic
- [ ] Fallback to default locale
- [ ] Works without translation files (uses keys)

### Guards
- [ ] Works with `guard=web`
- [ ] Works with `guard=api`
- [ ] Works with `guard=sanctum`

### Cache Management
- [ ] Spatie PermissionRegistrar cache cleared after role changes
- [ ] Spatie PermissionRegistrar cache cleared after permission changes
- [ ] Package cache cleared after matrix sync
- [ ] Cache tags used when supported

### Migrations
- [ ] Migrations publish cleanly
- [ ] Migrations run without errors
- [ ] Support tenant scoping (team_id strategy)
- [ ] Support tenant scoping (tenant_id strategy)

---

## 🚀 DEPLOYMENT CHECKLIST

### Pre-Release
- [ ] All tests passing (56/56)
- [ ] Code coverage ≥ 80%
- [ ] Documentation complete
- [ ] CHANGELOG updated
- [ ] Version bumped to 2.0.0

### Release
- [ ] Tag version: `git tag v2.0.0`
- [ ] Push to GitHub: `git push origin v2.0.0`
- [ ] Create GitHub release with notes
- [ ] Publish to Packagist

### Post-Release
- [ ] Monitor issues
- [ ] Update documentation site
- [ ] Announce on social media
- [ ] Create video tutorial

---

**Blueprint Version:** 1.0  
**Created:** 2025-12-19  
**Author:** Senior Laravel Package Maintainer + QA Lead

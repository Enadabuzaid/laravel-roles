# 🛡️ Laravel Roles & Permissions

[![Tests](https://img.shields.io/badge/tests-32%2F32%20passing-success)](https://github.com/enadstack/laravel-roles)
[![Version](https://img.shields.io/badge/version-1.1.1-blue)](https://github.com/enadstack/laravel-roles/releases)
[![PHP](https://img.shields.io/badge/php-%5E8.2-purple)](https://php.net)
[![Laravel](https://img.shields.io/badge/laravel-%5E12.0-red)](https://laravel.com)
[![Spatie](https://img.shields.io/badge/spatie-v6.0-green)](https://github.com/spatie/laravel-permission)
[![License](https://img.shields.io/badge/license-MIT-brightgreen)](LICENSE)

> **A production-ready Laravel package for managing roles and permissions with a clean API, service layer architecture, and multi-tenancy support.**

Built on top of [Spatie Laravel Permission](https://github.com/spatie/laravel-permission), this package provides everything you need to implement a complete role and permission system in your Laravel 12+ application.

---

## 📋 Table of Contents

- [✨ Features](#-features)
- [📦 Installation](#-installation)
- [⚙️ Configuration](#️-configuration)
- [🚀 Quick Start](#-quick-start)
- [📡 API Reference](#-api-reference)
- [💼 Usage Examples](#-usage-examples)
- [🏢 Multi-Tenancy](#-multi-tenancy)
- [🔒 Authorization & Security](#-authorization--security)
- [🔧 Advanced Usage](#-advanced-usage)
- [🧪 Testing](#-testing)
- [📚 FAQ](#-faq)
- [🤝 Contributing](#-contributing)

---

## ✨ Features

### 🎯 Core Features

- ✅ **Complete REST API** - 35+ endpoints for roles and permissions management
- ✅ **Service Layer Architecture** - Clean, testable business logic separated from controllers
- ✅ **Multi-Tenancy Support** - Single, team-scoped, or multi-database modes
- ✅ **Internationalization (i18n)** - Multi-language labels and descriptions
- ✅ **Permission Matrix** - Visual grid of roles × permissions
- ✅ **Soft Deletes** - Recover accidentally deleted roles/permissions
- ✅ **Bulk Operations** - Efficient mass delete, restore, and force delete
- ✅ **Event-Driven** - 6 domain events for extensibility
- ✅ **Config-Driven Seeding** - Sync permissions from config to database
- ✅ **100% Test Coverage** - 32 passing integration tests
- ✅ **PSR-4 Autoloading** - Modern PHP standards
- ✅ **Laravel 12 Ready** - Built for the latest Laravel

### 🔐 Role Management

- Create, read, update, delete (CRUD) operations
- Soft delete with restore functionality
- Force delete for permanent removal
- Bulk operations (delete, restore, force delete)
- Clone roles with permissions
- Assign/sync permissions to roles
- Add/remove single permissions (idempotent)
- Role statistics (total, active, deleted, with/without permissions)
- Recent roles endpoint

### 🎫 Permission Management

- CRUD operations for permissions
- Permission grouping (e.g., `users`, `posts`, `offers`)
- Search and filter by group
- Soft deletes with restore
- Permission matrix (roles × permissions grid)
- Grouped permissions view (cached)
- Permission statistics
- Recent permissions endpoint
- Bulk force delete

### 🏗️ Architecture

- **Controllers**: Handle HTTP requests and responses
- **Services**: Business logic layer (RoleService, PermissionService)
- **Policies**: Authorization logic (RolePolicy, PermissionPolicy)
- **FormRequests**: Input validation
- **API Resources**: Consistent JSON transformation
- **Events**: Domain events for extensibility
- **Models**: Eloquent models extending Spatie
- **Traits**: Reusable behaviors (HasTenantScope)
- **Commands**: Artisan commands (install, sync)

---

## 📦 Installation

### Requirements

- PHP >= 8.2
- Laravel >= 12.0
- Spatie Laravel Permission ^6.0

### Step 1: Install via Composer

```bash
composer require enadstack/laravel-roles
```

### Step 2: Run the Interactive Installer

> **⚠️ IMPORTANT**: Only run this command during **initial installation**, NOT when upgrading!

```bash
php artisan roles:install
```

The installer will guide you through:

1. ✅ Publishing Spatie Permission config and migrations
2. ✅ Publishing package config (`config/roles.php`)
3. ✅ Asking if you want multi-language support (i18n)
4. ✅ Asking which tenancy mode (single/team-scoped/multi-database)
5. ✅ Running migrations
6. ✅ Optionally seeding initial roles & permissions

**Installer Options:**

```bash
# Install with seeds automatically
php artisan roles:install --with-seeds

# Specify custom team key for team-scoped mode
php artisan roles:install --team-key=tenant_id
```

### Step 3: Configure (Optional)

Edit `config/roles.php` to customize settings (see [Configuration](#️-configuration) section).

---

## 🔄 Upgrading

### Upgrading to v1.1.1 from v1.0.x

```bash
# Update the package
composer update enadstack/laravel-roles

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan permission:cache-reset
```

> **✅ That's it!** No additional steps needed. Your `config/roles.php` will be preserved.  
> **DO NOT** run `php artisan roles:install` again unless you want to reconfigure from scratch.

### What's New in v1.1.1

- ✅ Enhanced documentation
- ✅ Bug fixes for test suite
- ✅ Improved authorization handling
- ✅ All existing functionality works exactly the same
- ✅ No breaking changes

---

## ⚙️ Configuration

After installation, your `config/roles.php` will contain all available options:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Internationalization (i18n)
    |--------------------------------------------------------------------------
    */
    'i18n' => [
        'enabled' => false,  // Set to true for multi-language support
        'locales' => ['en', 'ar'],
        'default' => 'en',
        'fallback' => 'en',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Guard
    |--------------------------------------------------------------------------
    */
    'guard' => env('ROLES_GUARD', 'web'),

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    | 
    | Modes:
    | - 'single': No multi-tenancy (default)
    | - 'team_scoped': One database, scope by team/tenant FK
    | - 'multi_database': Each tenant has its own database
    */
    'tenancy' => [
        'mode' => 'single',
        'team_foreign_key' => 'team_id',
        'provider' => null, // e.g., 'stancl/tenancy'
    ],

    /*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'prefix' => 'admin/acl',
        'middleware' => ['api', 'auth:sanctum'],
        'guard' => env('ROLES_GUARD', 'web'),
        'expose_me' => true, // Enable /me/roles, /me/permissions endpoints
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 300, // seconds (5 minutes)
        'keys' => [
            'grouped_permissions' => 'laravel_roles.grouped_permissions',
            'permission_matrix' => 'laravel_roles.permission_matrix',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Seed Data (for roles:sync command)
    |--------------------------------------------------------------------------
    */
    'seed' => [
        'roles' => ['super-admin', 'admin', 'manager', 'user'],
        
        'permission_groups' => [
            'roles' => ['list', 'create', 'show', 'update', 'delete', 'restore', 'force-delete'],
            'users' => ['list', 'create', 'show', 'update', 'delete', 'restore', 'force-delete'],
            'permissions' => ['list', 'show'],
        ],
        
        'map' => [
            'super-admin' => ['*'], // All permissions
            'admin' => ['users.*', 'roles.*'],
            'manager' => ['users.list', 'users.show'],
            'user' => [],
        ],
        
        // Optional: i18n labels and descriptions
        'role_descriptions' => [
            'super-admin' => 'Full system access',
            'admin' => 'Manage users and content',
            'user' => 'Standard account',
        ],
    ],
];
```

---

## 🚀 Quick Start

### 1. Create Your First Role

**API Request:**
```bash
POST /admin/acl/roles
Content-Type: application/json
Authorization: Bearer YOUR_TOKEN

{
  "name": "editor",
  "label": {
    "en": "Content Editor",
    "ar": "محرر المحتوى"
  },
  "description": {
    "en": "Can edit and publish content"
  },
  "guard_name": "web"
}
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "name": "editor",
    "guard_name": "web",
    "label": {"en": "Content Editor", "ar": "محرر المحتوى"},
    "description": {"en": "Can edit and publish content"},
    "permissions_count": 0,
    "users_count": 0,
    "created_at": "2025-12-01T10:00:00Z",
    "updated_at": "2025-12-01T10:00:00Z",
    "deleted_at": null
  }
}
```

### 2. Create Permissions

```bash
POST /admin/acl/permissions

{
  "name": "posts.create",
  "group": "posts",
  "label": {"en": "Create Posts"},
  "description": {"en": "Allows creating blog posts"},
  "group_label": {"en": "Posts Management"}
}
```

### 3. Assign Permissions to Role

```bash
POST /admin/acl/roles/1/permissions

{
  "permission_ids": [1, 2, 3, 4, 5]
}
```

**Response:**
```json
{
  "message": "Permissions assigned successfully",
  "role": {
    "id": 1,
    "name": "editor",
    "permissions_count": 5,
    ...
  }
}
```

### 4. Assign Role to User

```php
use Enadstack\LaravelRoles\Models\Role;

// In your controller or service
$user = auth()->user();
$role = Role::findByName('editor');

$user->assignRole($role);
// or
$user->assignRole('editor');
```

### 5. Check User Permissions

```php
// Check if user has role
if ($user->hasRole('editor')) {
    // User is an editor
}

// Check if user has permission
if ($user->can('posts.create')) {
    // User can create posts
}

// In Blade templates
@role('editor')
    <p>You are an editor!</p>
@endrole

@can('posts.create')
    <button>Create Post</button>
@endcan
```

---

## 📡 API Reference

### Base URL

```
{APP_URL}/admin/acl
```

Default prefix is `/admin/acl`, configurable in `config/roles.php`.

---

### 📋 Roles Endpoints

#### List Roles

```http
GET /roles?per_page=20&search=editor&guard=web&sort=created_at&direction=desc&with_trashed=false
```

**Query Parameters:**
- `per_page` (int, optional): Items per page (1-100, default: 20)
- `search` (string, optional): Search by name or description
- `guard` (string, optional): Filter by guard name
- `sort` (string, optional): Sort field (id, name, created_at, etc.)
- `direction` (string, optional): Sort direction (asc, desc)
- `with_trashed` (bool, optional): Include soft-deleted roles
- `only_trashed` (bool, optional): Only soft-deleted roles

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "editor",
      "guard_name": "web",
      "label": {"en": "Editor"},
      "permissions_count": 5,
      "users_count": 12,
      "created_at": "2025-12-01T10:00:00Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

---

#### Create Role

```http
POST /roles
```

**Request Body:**
```json
{
  "name": "editor",
  "label": {"en": "Editor", "ar": "محرر"},
  "description": {"en": "Can edit content"},
  "guard_name": "web"
}
```

**Validation:**
- `name`: required, string, max:255, lowercase alphanumeric + dashes/underscores, unique per guard
- `label`: optional, array (JSON object with locale keys)
- `description`: optional, array
- `guard_name`: optional, string, in:web,api,admin (default: web)

---

#### Show Role

```http
GET /roles/{id}
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "name": "editor",
    "guard_name": "web",
    "label": {"en": "Editor"},
    "description": {"en": "Can edit content"},
    "permissions_count": 5,
    "users_count": 12,
    "created_at": "2025-12-01T10:00:00Z",
    "updated_at": "2025-12-01T10:00:00Z",
    "deleted_at": null
  }
}
```

---

#### Update Role

```http
PUT /roles/{id}
```

**Request Body:** Same as Create Role

---

#### Delete Role (Soft Delete)

```http
DELETE /roles/{id}
```

**Response:**
```json
{
  "message": "Role deleted successfully"
}
```

---

#### Restore Role

```http
POST /roles/{id}/restore
```

---

#### Force Delete Role (Permanent)

```http
DELETE /roles/{id}/force
```

> **Warning**: This permanently deletes the role and cannot be undone.

---

#### Bulk Delete Roles

```http
POST /roles/bulk-delete

{
  "ids": [1, 2, 3]
}
```

**Response:**
```json
{
  "message": "Bulk delete completed",
  "results": {
    "success": [1, 2],
    "failed": [
      {"id": 3, "reason": "Not found"}
    ]
  }
}
```

---

#### Bulk Restore Roles

```http
POST /roles/bulk-restore

{
  "ids": [1, 2, 3]
}
```

---

#### Bulk Force Delete Roles

```http
POST /roles/bulk-force-delete

{
  "ids": [1, 2, 3]
}
```

---

#### Assign Permissions to Role

```http
POST /roles/{id}/permissions

{
  "permission_ids": [1, 2, 3, 4, 5]
}
```

**Response:**
```json
{
  "message": "Permissions assigned successfully",
  "role": {
    "id": 1,
    "name": "editor",
    "permissions_count": 5,
    ...
  }
}
```

---

#### Get Role's Permissions

```http
GET /roles/{id}/permissions
```

**Response:**
```json
[
  {"id": 1, "name": "posts.create", "group": "posts"},
  {"id": 2, "name": "posts.update", "group": "posts"}
]
```

---

#### Add Single Permission to Role

```http
POST /roles/{id}/permission

{
  "permission_id": 1
}
```

Idempotent: won't error if permission already assigned.

---

#### Remove Single Permission from Role

```http
DELETE /roles/{id}/permission

{
  "permission_id": 1
}
```

Idempotent: won't error if permission not assigned.

---

#### Clone Role

```http
POST /roles/{id}/clone

{
  "name": "editor-copy",
  "label": {"en": "Editor Copy"},
  "description": {"en": "Cloned from editor"},
  "guard_name": "web"
}
```

Creates a new role with all permissions from the source role.

---

#### Role Statistics

```http
GET /roles-stats
```

**Response:**
```json
{
  "total": 10,
  "active": 8,
  "deleted": 2,
  "with_permissions": 7,
  "without_permissions": 3
}
```

---

#### Recent Roles

```http
GET /roles-recent?limit=10
```

**Response:**
```json
{
  "data": [
    {"id": 10, "name": "newest-role", "created_at": "2025-12-01T10:00:00Z"},
    {"id": 9, "name": "second-newest", "created_at": "2025-11-30T10:00:00Z"}
  ]
}
```

---

#### Permissions Grouped by Role

```http
GET /roles-permissions
```

**Response:**
```json
{
  "editor": {
    "role_id": 1,
    "permissions": [
      {"id": 1, "name": "posts.create"},
      {"id": 2, "name": "posts.update"}
    ]
  },
  "admin": {
    "role_id": 2,
    "permissions": [...]
  }
}
```

---

### 🎫 Permissions Endpoints

#### List Permissions

```http
GET /permissions?per_page=20&search=posts&group=posts&guard=web
```

**Query Parameters:**
- `per_page`: Items per page
- `search`: Search by name, label, description, or group
- `group`: Filter by group (e.g., "posts", "users")
- `guard`: Filter by guard name
- `sort`, `direction`, `with_trashed`, `only_trashed`: Same as roles

---

#### Create Permission

```http
POST /permissions

{
  "name": "posts.create",
  "group": "posts",
  "label": {"en": "Create Posts", "ar": "إنشاء المنشورات"},
  "description": {"en": "Allows creating blog posts"},
  "group_label": {"en": "Posts Management"},
  "guard_name": "web"
}
```

**Validation:**
- `name`: required, string, max:255, format `group.action`, unique per guard
- `group`: optional, string, max:255, lowercase alphanumeric + dashes/underscores
- `label`: optional, array
- `description`: optional, array
- `group_label`: optional, array
- `guard_name`: optional, default: web

---

#### Show Permission

```http
GET /permissions/{id}
```

---

#### Update Permission

```http
PUT /permissions/{id}
```

---

#### Delete Permission (Soft Delete)

```http
DELETE /permissions/{id}
```

---

#### Restore Permission

```http
POST /permissions/{id}/restore
```

---

#### Force Delete Permission

```http
DELETE /permissions/{id}/force
```

---

#### Bulk Force Delete Permissions

```http
POST /permissions/bulk-force-delete

{
  "ids": [1, 2, 3]
}
```

---

#### Permission Statistics

```http
GET /permissions-stats
```

**Response:**
```json
{
  "total": 50,
  "active": 48,
  "deleted": 2,
  "assigned": 40,
  "unassigned": 10,
  "by_group": {
    "posts": 10,
    "users": 15,
    "roles": 7
  }
}
```

---

#### Recent Permissions

```http
GET /permissions-recent?limit=10
```

---

#### Grouped Permissions (Cached)

```http
GET /permission-groups
```

**Response:**
```json
{
  "posts": {
    "label": {"en": "Posts Management"},
    "permissions": [
      {"id": 1, "name": "posts.create", "label": {"en": "Create Posts"}},
      {"id": 2, "name": "posts.update", "label": {"en": "Update Posts"}}
    ]
  },
  "users": {
    "label": {"en": "User Management"},
    "permissions": [...]
  }
}
```

Cached for performance (default TTL: 5 minutes).

---

#### Permission Matrix (Cached)

```http
GET /permissions-matrix
```

**Response:**
```json
{
  "data": {
    "roles": [
      {"id": 1, "name": "editor", "label": {"en": "Editor"}},
      {"id": 2, "name": "admin", "label": {"en": "Admin"}}
    ],
    "matrix": [
      {
        "permission_id": 1,
        "permission_name": "posts.create",
        "permission_label": {"en": "Create Posts"},
        "permission_group": "posts",
        "roles": {
          "editor": {"role_id": 1, "has_permission": true},
          "admin": {"role_id": 2, "has_permission": true}
        }
      },
      {
        "permission_id": 2,
        "permission_name": "posts.delete",
        "permission_label": {"en": "Delete Posts"},
        "permission_group": "posts",
        "roles": {
          "editor": {"role_id": 1, "has_permission": false},
          "admin": {"role_id": 2, "has_permission": true}
        }
      }
    ],
    "generated_at": "2025-12-01T10:00:00Z"
  }
}
```

Perfect for building a permission management UI with checkboxes.

---

### 👤 Current User Endpoints

> Only available if `config('roles.routes.expose_me')` is `true`

#### Get Current User's Roles

```http
GET /me/roles
```

**Response:**
```json
{
  "roles": [
    {"id": 1, "name": "editor", "label": {"en": "Editor"}}
  ]
}
```

---

#### Get Current User's Permissions

```http
GET /me/permissions
```

**Response:**
```json
{
  "permissions": [
    {"id": 1, "name": "posts.create", "group": "posts"},
    {"id": 2, "name": "posts.update", "group": "posts"}
  ]
}
```

---

#### Get Current User's Abilities

```http
GET /me/abilities
```

**Response:**
```json
{
  "roles": [...],
  "permissions": [...],
  "abilities": [
    "posts.create",
    "posts.update",
    "users.list"
  ]
}
```

---

## 💼 Usage Examples

### Example 1: Blog Platform

**Goal**: Setup roles for a blog (admin, editor, author, reader)

```php
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Services\RoleService;

$roleService = app(RoleService::class);

// 1. Create permissions
$permissions = [
    Permission::create(['name' => 'posts.list', 'group' => 'posts']),
    Permission::create(['name' => 'posts.create', 'group' => 'posts']),
    Permission::create(['name' => 'posts.update', 'group' => 'posts']),
    Permission::create(['name' => 'posts.delete', 'group' => 'posts']),
    Permission::create(['name' => 'posts.publish', 'group' => 'posts']),
];

// 2. Create roles
$admin = $roleService->create(['name' => 'admin']);
$editor = $roleService->create(['name' => 'editor']);
$author = $roleService->create(['name' => 'author']);
$reader = $roleService->create(['name' => 'reader']);

// 3. Assign permissions
$roleService->assignPermissions($admin, [1, 2, 3, 4, 5]); // All permissions
$roleService->assignPermissions($editor, [1, 2, 3, 4, 5]); // All permissions
$roleService->assignPermissions($author, [1, 2, 3]); // List, create, update own
$roleService->assignPermissions($reader, [1]); // List only

// 4. Assign role to user
auth()->user()->assignRole('author');
```

---

### Example 2: E-Commerce Platform

**Config-Driven Setup** (Recommended for CI/CD)

```php
// config/roles.php
'seed' => [
    'permission_groups' => [
        'products' => ['list', 'create', 'update', 'delete', 'publish'],
        'orders' => ['list', 'show', 'update', 'cancel', 'refund'],
        'customers' => ['list', 'show', 'update', 'ban'],
        'reports' => ['view', 'export'],
    ],
    
    'map' => [
        'super-admin' => ['*'],
        'store-manager' => ['products.*', 'orders.*', 'reports.*'],
        'support-agent' => ['customers.*', 'orders.list', 'orders.show', 'orders.update'],
        'content-editor' => ['products.list', 'products.update'],
    ],
],
```

```bash
# Run in deployment pipeline
php artisan roles:sync
```

---

### Example 3: Multi-Tenant SaaS

**Team-Scoped Tenancy**

```php
// 1. Configure tenancy in config/roles.php
'tenancy' => [
    'mode' => 'team_scoped',
    'team_foreign_key' => 'team_id',
],

// 2. Set tenant context in middleware
namespace App\Http\Middleware;

class SetTenantContext
{
    public function handle($request, $next)
    {
        if ($user = auth()->user()) {
            app()->instance('permission.team_id', $user->team_id);
        }
        
        return $next($request);
    }
}

// 3. Register middleware in app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ...
        \App\Http\Middleware\SetTenantContext::class,
    ],
];

// 4. Create tenant-specific roles
$role = Role::create([
    'name' => 'team-admin',
    'team_id' => $user->team_id, // Auto-set if using middleware
]);

// 5. Query roles (automatically scoped)
$roles = Role::all(); // Only current team + global roles

// 6. Super-admin can see all
$allRoles = Role::forAllTenants()->get();
```

---

### Example 4: Using Service Layer

**Recommended for complex logic**

```php
namespace App\Services;

use Enadstack\LaravelRoles\Services\RoleService;
use Enadstack\LaravelRoles\Services\PermissionService;

class OrganizationSetupService
{
    public function __construct(
        private RoleService $roleService,
        private PermissionService $permissionService,
    ) {}
    
    public function setupOrganization(Organization $org): void
    {
        // Create organization-specific role
        $orgAdminRole = $this->roleService->create([
            'name' => "org-admin-{$org->id}",
            'label' => ['en' => "Organization Admin: {$org->name}"],
            'team_id' => $org->id,
        ]);
        
        // Create permissions
        $permissions = collect([
            'org.settings.update',
            'org.members.invite',
            'org.billing.manage',
        ])->map(fn($name) => $this->permissionService->create([
            'name' => $name,
            'group' => 'organization',
            'team_id' => $org->id,
        ]));
        
        // Assign permissions to role
        $this->roleService->assignPermissions(
            $orgAdminRole,
            $permissions->pluck('id')->toArray()
        );
        
        // Assign role to organization owner
        $org->owner->assignRole($orgAdminRole);
    }
}
```

---

### Example 5: Listening to Events

**Audit Logging Example**

```php
// app/Providers/EventServiceProvider.php
use Enadstack\LaravelRoles\Events\RoleCreated;
use Enadstack\LaravelRoles\Events\RoleUpdated;
use Enadstack\LaravelRoles\Events\PermissionsAssignedToRole;

protected $listen = [
    RoleCreated::class => [
        \App\Listeners\LogRoleCreated::class,
    ],
    RoleUpdated::class => [
        \App\Listeners\LogRoleUpdated::class,
    ],
    PermissionsAssignedToRole::class => [
        \App\Listeners\LogPermissionsAssigned::class,
    ],
];

// app/Listeners/LogRoleCreated.php
namespace App\Listeners;

use Enadstack\LaravelRoles\Events\RoleCreated;
use Illuminate\Support\Facades\Log;

class LogRoleCreated
{
    public function handle(RoleCreated $event): void
    {
        Log::info('Role created', [
            'role_id' => $event->role->id,
            'role_name' => $event->role->name,
            'created_by' => auth()->id(),
            'timestamp' => now(),
        ]);
        
        // Or save to audit log table
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'role.created',
            'model_type' => 'role',
            'model_id' => $event->role->id,
            'data' => $event->role->toArray(),
        ]);
    }
}
```

---

## 🏢 Multi-Tenancy

This package supports three tenancy modes:

### Mode 1: Single (No Multi-Tenancy)

**Use Case**: Single-tenant applications, admin panels

**Config:**
```php
'tenancy' => [
    'mode' => 'single',
],
```

**Behavior**: Standard Spatie behavior, no tenant scoping.

---

### Mode 2: Team-Scoped (Shared Database)

**Use Case**: SaaS apps with multiple teams in one database (like Slack workspaces)

**Config:**
```php
'tenancy' => [
    'mode' => 'team_scoped',
    'team_foreign_key' => 'team_id',
],
```

**Setup:**

1. **Migrations add `team_id` column**:
```sql
ALTER TABLE roles ADD COLUMN team_id BIGINT UNSIGNED NULL;
ALTER TABLE permissions ADD COLUMN team_id BIGINT UNSIGNED NULL;
```

2. **Set tenant context** (in middleware or controller):
```php
app()->instance('permission.team_id', auth()->user()->team_id);
```

3. **Queries are automatically scoped**:
```php
// Returns current team's roles + global roles (team_id = NULL)
$roles = Role::all();
```

4. **Global records** (`team_id = NULL`) are shared across all tenants:
```php
// Create global role (available to all teams)
Role::create(['name' => 'user', 'team_id' => null]);

// Create team-specific role
Role::create(['name' => 'team-admin', 'team_id' => $teamId]);
```

5. **Scope methods**:
```php
// Bypass scope (super-admin only)
$allRoles = Role::forAllTenants()->get();

// Only tenant-specific (exclude global)
$tenantRoles = Role::onlyTenantSpecific()->get();

// Only global
$globalRoles = Role::onlyGlobal()->get();

// Specific tenant
$teamRoles = Role::forTenant($teamId)->get();
```

6. **Helper methods on models**:
```php
$role->isGlobal(); // true if team_id is NULL
$role->belongsToTenant($teamId); // true if role belongs to team
```

---

### Mode 3: Multi-Database (Stancl Tenancy)

**Use Case**: Full database isolation per tenant (like WordPress.com)

**Config:**
```php
'tenancy' => [
    'mode' => 'multi_database',
    'provider' => 'stancl/tenancy',
],
```

**Setup:**

1. **Install Stancl Tenancy**:
```bash
composer require stancl/tenancy
php artisan tenancy:install
```

2. **Migrations run per-tenant**:
```bash
php artisan tenants:migrate
```

3. **No `team_id` needed** (database isolation provides tenancy)

4. **Each tenant has own roles/permissions tables**

**Note**: Multi-database mode is configured but not fully tested. Contributions welcome!

---

## 🔒 Authorization & Security

### Authorization Policies

This package includes comprehensive authorization policies for all operations.

#### RolePolicy

| Method | Permission Check | Special Rules |
|--------|------------------|---------------|
| `viewAny` | `roles.list` OR super-admin | - |
| `view` | `roles.show` OR super-admin | Must be in same team |
| `create` | `roles.create` OR super-admin | - |
| `update` | `roles.update` OR super-admin | Protect `super-admin`, `admin` roles |
| `delete` | `roles.delete` OR super-admin | **Never** delete `super-admin`, `admin`, `user` |
| `restore` | `roles.restore` OR super-admin | - |
| `forceDelete` | super-admin ONLY | **Never** force delete system roles |
| `bulkDelete` | `roles.bulk-delete` OR super-admin | - |
| `assignPermissions` | `roles.assign-permissions` OR super-admin | Protect `super-admin` role |
| `clone` | `roles.clone` OR super-admin | **Never** clone `super-admin` |

#### PermissionPolicy

| Method | Permission Check |
|--------|------------------|
| `viewAny` | `permissions.list` OR super-admin |
| `view` | `permissions.show` OR super-admin |
| `create` | `permissions.create` OR super-admin |
| `update` | `permissions.update` OR super-admin |
| `delete` | `permissions.delete` OR super-admin |
| `restore` | `permissions.restore` OR super-admin |
| `forceDelete` | super-admin ONLY |
| `bulkDelete` | `permissions.bulk-delete` OR super-admin |

### Security Features

✅ **System Role Protection**: Cannot delete/modify `super-admin`, `admin`, `user` roles  
✅ **Tenant Isolation**: Policies enforce same-team checks in team-scoped mode  
✅ **Input Validation**: All inputs validated via FormRequests  
✅ **SQL Injection Protection**: Eloquent ORM prevents SQL injection  
✅ **Mass Assignment Protection**: Models use `$fillable` arrays  
✅ **Soft Deletes**: Accidental deletions can be recovered  
✅ **Authorization Events**: All operations emit events for audit logging  

### Recommended Security Practices

1. **Enable Audit Logging**:
```php
// Listen to events and log changes
Event::listen([
    RoleCreated::class,
    RoleUpdated::class,
    RoleDeleted::class,
], function ($event) {
    AuditLog::create([...]);
});
```

2. **Add Rate Limiting**:
```php
// In routes/web.php or routes/api.php
Route::middleware(['throttle:60,1'])->group(function () {
    // Your routes
});
```

3. **Use 2FA for Super-Admins**:
```php
// Require 2FA for super-admin routes
Route::middleware(['2fa'])->group(function () {
    // Super-admin only routes
});
```

4. **Whitelist Permission Groups**:
```php
// In config/roles.php
'allowed_groups' => ['users', 'posts', 'roles', 'permissions'],
```

---

## 🔧 Advanced Usage

### Using the Sync Command

The `roles:sync` command syncs permissions from config to database (idempotent).

**Use Case**: CI/CD deployments

```bash
# Sync permissions (creates missing, updates existing)
php artisan roles:sync

# Sync and prune permissions not in config
php artisan roles:sync --prune
```

**Config Example**:
```php
// config/roles.php
'seed' => [
    'permission_groups' => [
        'posts' => ['list', 'create', 'update', 'delete', 'publish'],
        'users' => ['list', 'show', 'update', 'ban'],
    ],
    
    'map' => [
        'admin' => ['*'],
        'editor' => ['posts.*'],
        'moderator' => ['users.ban'],
    ],
],
```

**How it works**:
1. Reads `permission_groups` from config
2. Creates permissions if they don't exist (by name + guard)
3. Maps permissions to roles via `map`
4. If `--prune`, deletes permissions not in config

---

### Custom User Model

If your user model is not `App\Models\User`, configure Spatie:

```php
// config/permission.php
'models' => [
    'user' => \App\Models\Admin::class,
],
```

---

### Multiple Guards

This package fully supports multiple guards (web, api, admin, etc.).

```php
// Create role with specific guard
$apiRole = Role::create([
    'name' => 'api-client',
    'guard_name' => 'api',
]);

// Create permission with specific guard
$apiPerm = Permission::create([
    'name' => 'api.read',
    'guard_name' => 'api',
]);

// Assign to user with same guard
$apiUser = User::find(1);
$apiUser->guard_name = 'api';
$apiUser->assignRole($apiRole);
```

---

### Caching

This package caches expensive operations:

- **Permission Matrix**: Cached for `cache.ttl` seconds (default: 5 mins)
- **Grouped Permissions**: Cached for `cache.ttl` seconds

**Cache Invalidation**:
- Automatic on role/permission changes (via events)
- Manual: `php artisan permission:cache-reset`

**Cache Tags** (if supported by driver):
- Uses `laravel_roles` tag for easy flushing
- Falls back to key-based cache if tags not supported

---

### Extending Models

You can extend the package models:

```php
namespace App\Models;

use Enadstack\LaravelRoles\Models\Role as BaseRole;

class Role extends BaseRole
{
    // Add custom methods
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
```

Then configure Spatie to use your model:

```php
// config/permission.php
'models' => [
    'role' => \App\Models\Role::class,
    'permission' => \App\Models\Permission::class,
],
```

---

## 🧪 Testing

### Running Tests

```bash
# Run all tests
composer test

# or
vendor/bin/pest

# Run with coverage
vendor/bin/pest --coverage

# Run specific test file
vendor/bin/pest tests/Feature/RoleApiTest.php
```

### Test Suite

| Test Suite | Tests | Coverage |
|------------|-------|----------|
| `RoleApiTest` | 15 | Role CRUD, bulk ops, permissions, stats, clone |
| `PermissionApiTest` | 7 | Permission CRUD, groups, matrix, stats |
| `RoleEndpointsTest` | 1 | Fine-grained permission operations |
| `PermissionMatrixTest` | 7 | Matrix generation, caching |
| `SyncCommandTest` | 2 | Config sync, pruning |

**Total: 32 tests, 100% passing** ✅

---

## 📚 FAQ

### Q: Can I use this without the API endpoints?

Yes! You can disable routes:

```php
// config/roles.php
'routes' => [
    'prefix' => null, // Disables routes
],
```

Then use the service layer directly in your code.

---

### Q: How do I add a new permission group?

Option 1: **Via API**
```bash
POST /admin/acl/permissions
{
  "name": "offers.create",
  "group": "offers"
}
```

Option 2: **Via Config** (for CI/CD)
```php
// config/roles.php
'seed' => [
    'permission_groups' => [
        'offers' => ['list', 'create', 'update', 'delete'],
    ],
],
```

Then run: `php artisan roles:sync`

---

### Q: How do I protect system roles from accidental deletion?

The package already protects `super-admin`, `admin`, and `user` roles via policies. You can add more:

```php
// app/Policies/CustomRolePolicy.php
public function delete(Authenticatable $user, Role $role): bool
{
    $systemRoles = ['super-admin', 'admin', 'user', 'moderator'];
    
    if (in_array($role->name, $systemRoles, true)) {
        return false;
    }
    
    return $user->can('roles.delete') || $user->hasRole('super-admin');
}
```

---

### Q: How do I implement audit logging?

Listen to domain events:

```php
// app/Providers/EventServiceProvider.php
use Enadstack\LaravelRoles\Events\RoleCreated;

protected $listen = [
    RoleCreated::class => [
        \App\Listeners\LogRoleCreated::class,
    ],
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
        'user_agent' => request()->userAgent(),
    ]);
}
```

---

### Q: Can I use this with Filament Admin Panel?

Yes! Filament plays nicely with Spatie Permission. Just use the package's models and policies in your Filament resources.

```php
// app/Filament/Resources/RoleResource.php
use Enadstack\LaravelRoles\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;
    
    // ... Filament resource definition
}
```

---

### Q: How do I migrate from pure Spatie to this package?

This package extends Spatie, so migration is seamless:

1. Install package: `composer require enadstack/laravel-roles`
2. Run migrations: `php artisan migrate` (adds soft deletes, i18n columns)
3. Your existing roles/permissions are preserved
4. Start using package API/services

---

### Q: Does this work with Laravel 11?

Currently tested with Laravel 12. Laravel 11 compatibility untested but likely works with minor adjustments.

---

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. **Fork the repository**
2. **Create a feature branch**: `git checkout -b feature/my-feature`
3. **Write tests** for your changes
4. **Ensure tests pass**: `composer test`
5. **Follow PSR-12 coding standards**
6. **Commit with clear messages**: `git commit -m "Add: My feature description"`
7. **Push to your fork**: `git push origin feature/my-feature`
8. **Create a Pull Request**

### Development Setup

```bash
git clone https://github.com/enadstack/laravel-roles.git
cd laravel-roles
composer install
vendor/bin/pest
```

---

## 📄 License

This package is open-source software licensed under the [MIT license](LICENSE).

---

## 🙏 Credits

- **Author**: [Enad Abuzaid](https://github.com/enadstack)
- **Built on**: [Spatie Laravel Permission](https://github.com/spatie/laravel-permission)
- **Inspired by**: Laravel Jetstream, Laravel Nova, Filament

---

## 🔗 Links

- **Repository**: https://github.com/enadstack/laravel-roles
- **Issues**: https://github.com/enadstack/laravel-roles/issues
- **Changelog**: [CHANGELOG.md](CHANGELOG.md)
- **Spatie Permission Docs**: https://spatie.be/docs/laravel-permission

---

## 🌟 Star this repository if you find it useful!

[![GitHub Stars](https://img.shields.io/github/stars/enadstack/laravel-roles?style=social)](https://github.com/enadstack/laravel-roles)

---

**Made with ❤️ by [Enad Abuzaid](https://github.com/enadstack)**


# Testing Strategy & Implementation - Laravel Roles v2.0

## 🧪 COMPLETE TESTING GUIDE

### 1. TEST ENVIRONMENT SETUP

**File:** `tests/TestCase.php`

```php
<?php

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Enadstack\LaravelRoles\Providers\RolesServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Run migrations
        $this->artisan('migrate');
        
        // Clear cache
        Artisan::call('permission:cache-reset');
    }
    
    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            RolesServiceProvider::class,
        ];
    }
    
    protected function defineEnvironment($app): void
    {
        // Database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        // Package config
        $app['config']->set('roles.tenancy.mode', 'single');
        $app['config']->set('roles.i18n.enabled', true);
        $app['config']->set('roles.i18n.locales', ['en', 'ar']);
        $app['config']->set('roles.guard', 'web');
        $app['config']->set('roles.cache.enabled', true);
    }
    
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }
    
    /**
     * Create authenticated user with permissions
     */
    protected function authenticatedUser(array $permissions = []): \App\Models\User
    {
        $user = \App\Models\User::factory()->create();
        
        if (!empty($permissions)) {
            foreach ($permissions as $permission) {
                $user->givePermissionTo($permission);
            }
        }
        
        $this->actingAs($user);
        
        return $user;
    }
    
    /**
     * Create role with permissions
     */
    protected function createRoleWithPermissions(string $name, array $permissions = []): \Spatie\Permission\Models\Role
    {
        $role = \Spatie\Permission\Models\Role::create(['name' => $name]);
        
        foreach ($permissions as $permission) {
            $perm = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
            $role->givePermissionTo($perm);
        }
        
        return $role;
    }
}
```

---

### 2. FEATURE TESTS

#### Test: Role CRUD Operations

**File:** `tests/Feature/RoleCrudTest.php`

```php
<?php

use Spatie\Permission\Models\Role;
use function Pest\Laravel\{postJson, getJson, putJson, deleteJson, assertDatabaseHas, assertDatabaseMissing};

beforeEach(function() {
    $this->user = $this->authenticatedUser(['roles.create', 'roles.update', 'roles.delete']);
});

it('lists all roles with pagination', function() {
    Role::factory()->count(20)->create();
    
    $response = getJson('/api/admin/acl/roles?per_page=10');
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'guard_name', 'created_at']
            ],
            'links',
            'meta' => ['current_page', 'total', 'per_page']
        ])
        ->assertJsonPath('meta.per_page', 10);
});

it('creates a role with valid data', function() {
    $response = postJson('/api/admin/acl/roles', [
        'name' => 'content-editor',
        'label' => ['en' => 'Content Editor', 'ar' => 'محرر المحتوى'],
        'description' => ['en' => 'Can edit content'],
        'guard_name' => 'web',
    ]);
    
    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'name', 'label', 'description']]);
    
    assertDatabaseHas('roles', [
        'name' => 'content-editor',
        'guard_name' => 'web',
    ]);
});

it('validates role name format', function() {
    $response = postJson('/api/admin/acl/roles', [
        'name' => 'Invalid Name!', // Contains invalid characters
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('prevents duplicate role names', function() {
    Role::create(['name' => 'editor', 'guard_name' => 'web']);
    
    $response = postJson('/api/admin/acl/roles', [
        'name' => 'editor',
        'guard_name' => 'web',
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('updates a role', function() {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    
    $response = putJson("/api/admin/acl/roles/{$role->id}", [
        'name' => 'senior-editor',
        'label' => ['en' => 'Senior Editor'],
    ]);
    
    $response->assertStatus(200);
    
    assertDatabaseHas('roles', [
        'id' => $role->id,
        'name' => 'senior-editor',
    ]);
});

it('soft deletes a role', function() {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    
    $response = deleteJson("/api/admin/acl/roles/{$role->id}");
    
    $response->assertStatus(204);
    
    expect($role->fresh()->trashed())->toBeTrue();
});

it('restores a soft-deleted role', function() {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $role->delete();
    
    $response = postJson("/api/admin/acl/roles/{$role->id}/restore");
    
    $response->assertStatus(200);
    
    expect($role->fresh()->trashed())->toBeFalse();
});

it('force deletes a role permanently', function() {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    
    $response = deleteJson("/api/admin/acl/roles/{$role->id}/force");
    
    $response->assertStatus(204);
    
    assertDatabaseMissing('roles', ['id' => $role->id]);
});

it('clears Spatie cache after role creation', function() {
    $this->spy(\Spatie\Permission\PermissionRegistrar::class)
        ->shouldReceive('forgetCachedPermissions')
        ->once();
    
    postJson('/api/admin/acl/roles', [
        'name' => 'test-role',
        'guard_name' => 'web',
    ]);
});

it('clears Spatie cache after role update', function() {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    
    $this->spy(\Spatie\Permission\PermissionRegistrar::class)
        ->shouldReceive('forgetCachedPermissions')
        ->once();
    
    putJson("/api/admin/acl/roles/{$role->id}", [
        'name' => 'senior-editor',
    ]);
});

it('clears Spatie cache after role deletion', function() {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    
    $this->spy(\Spatie\Permission\PermissionRegistrar::class)
        ->shouldReceive('forgetCachedPermissions')
        ->once();
    
    deleteJson("/api/admin/acl/roles/{$role->id}");
});
```

---

#### Test: Permission CRUD Operations

**File:** `tests/Feature/PermissionCrudTest.php`

```php
<?php

use Spatie\Permission\Models\Permission;
use function Pest\Laravel\{postJson, getJson, putJson, deleteJson, assertDatabaseHas};

beforeEach(function() {
    $this->user = $this->authenticatedUser(['permissions.create', 'permissions.update', 'permissions.delete']);
});

it('creates a permission with valid data', function() {
    $response = postJson('/api/admin/acl/permissions', [
        'name' => 'posts.create',
        'group' => 'posts',
        'label' => ['en' => 'Create Posts', 'ar' => 'إنشاء المنشورات'],
        'guard_name' => 'web',
    ]);
    
    $response->assertStatus(201);
    
    assertDatabaseHas('permissions', [
        'name' => 'posts.create',
        'group' => 'posts',
    ]);
});

it('validates permission name format', function() {
    $response = postJson('/api/admin/acl/permissions', [
        'name' => 'Invalid Permission!',
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('filters permissions by group', function() {
    Permission::create(['name' => 'posts.create', 'group' => 'posts']);
    Permission::create(['name' => 'users.create', 'group' => 'users']);
    
    $response = getJson('/api/admin/acl/permissions?group=posts');
    
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.group', 'posts');
});

it('searches permissions by name', function() {
    Permission::create(['name' => 'posts.create', 'group' => 'posts']);
    Permission::create(['name' => 'posts.update', 'group' => 'posts']);
    Permission::create(['name' => 'users.create', 'group' => 'users']);
    
    $response = getJson('/api/admin/acl/permissions?search=posts');
    
    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});
```

---

#### Test: Permission Matrix

**File:** `tests/Feature/PermissionMatrixTest.php`

```php
<?php

use Spatie\Permission\Models\{Role, Permission};
use function Pest\Laravel\{getJson, postJson};

beforeEach(function() {
    $this->user = $this->authenticatedUser(['permissions.list', 'permissions.update']);
    
    // Create test data
    $this->role1 = Role::create(['name' => 'admin']);
    $this->role2 = Role::create(['name' => 'editor']);
    
    $this->perm1 = Permission::create(['name' => 'posts.create', 'group' => 'posts']);
    $this->perm2 = Permission::create(['name' => 'posts.update', 'group' => 'posts']);
    
    $this->role1->givePermissionTo([$this->perm1, $this->perm2]);
    $this->role2->givePermissionTo($this->perm1);
});

it('returns permission matrix', function() {
    $response = getJson('/api/admin/acl/permissions-matrix');
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'roles' => [
                    '*' => ['id', 'name']
                ],
                'matrix' => [
                    '*' => ['permission_id', 'permission_name', 'group', 'roles']
                ],
                'generated_at'
            ]
        ]);
    
    $matrix = $response->json('data.matrix');
    
    expect($matrix[0]['roles'][$this->role1->id])->toBeTrue();
    expect($matrix[0]['roles'][$this->role2->id])->toBeTrue();
    expect($matrix[1]['roles'][$this->role1->id])->toBeTrue();
    expect($matrix[1]['roles'][$this->role2->id])->toBeFalse();
});

it('syncs permission matrix', function() {
    $response = postJson('/api/admin/acl/permissions-matrix/sync', [
        'matrix' => [
            [
                'role_id' => $this->role2->id,
                'permission_ids' => [$this->perm1->id, $this->perm2->id],
            ],
        ],
    ]);
    
    $response->assertStatus(200);
    
    expect($this->role2->fresh()->hasPermissionTo($this->perm2))->toBeTrue();
});

it('caches permission matrix', function() {
    // First call - should cache
    $response1 = getJson('/api/admin/acl/permissions-matrix');
    
    // Second call - should use cache
    $response2 = getJson('/api/admin/acl/permissions-matrix');
    
    expect($response1->json('data.generated_at'))
        ->toBe($response2->json('data.generated_at'));
});
```

---

#### Test: User-Role Assignment

**File:** `tests/Feature/UserRoleAssignmentTest.php`

```php
<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\{postJson, deleteJson, getJson};

beforeEach(function() {
    $this->admin = $this->authenticatedUser(['users.update']);
    $this->user = User::factory()->create();
    $this->role = Role::create(['name' => 'editor']);
});

it('assigns roles to user', function() {
    $response = postJson("/api/admin/acl/users/{$this->user->id}/roles/assign", [
        'role_ids' => [$this->role->id],
    ]);
    
    $response->assertStatus(200);
    
    expect($this->user->fresh()->hasRole('editor'))->toBeTrue();
});

it('syncs roles to user', function() {
    $role2 = Role::create(['name' => 'manager']);
    $this->user->assignRole($this->role);
    
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
    
    $response = deleteJson("/api/admin/acl/users/{$this->user->id}/roles/{$this->role->id}");
    
    $response->assertStatus(204);
    
    expect($this->user->fresh()->hasRole('editor'))->toBeFalse();
});

it('validates role_ids array', function() {
    $response = postJson("/api/admin/acl/users/{$this->user->id}/roles/assign", [
        'role_ids' => 'not-an-array',
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['role_ids']);
});

it('validates role existence', function() {
    $response = postJson("/api/admin/acl/users/{$this->user->id}/roles/assign", [
        'role_ids' => [99999],
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['role_ids.0']);
});
```

---

#### Test: Multi-Tenancy (Team Scoped)

**File:** `tests/Feature/TenancySwitchingTest.php`

```php
<?php

use Spatie\Permission\Models\{Role, Permission};
use function Pest\Laravel\{postJson, getJson};

beforeEach(function() {
    config(['roles.tenancy.mode' => 'team_scoped']);
    config(['roles.tenancy.team_foreign_key' => 'team_id']);
    
    $this->user = $this->authenticatedUser(['roles.create', 'roles.list']);
});

it('scopes roles to team context', function() {
    setPermissionsTeamId(1);
    $role1 = Role::create(['name' => 'admin']);
    
    setPermissionsTeamId(2);
    $role2 = Role::create(['name' => 'admin']);
    
    expect($role1->id)->not->toBe($role2->id);
    
    setPermissionsTeamId(1);
    expect(Role::count())->toBe(1);
    
    setPermissionsTeamId(2);
    expect(Role::count())->toBe(1);
});

it('prevents cross-tenant role access', function() {
    setPermissionsTeamId(1);
    $role = Role::create(['name' => 'admin']);
    
    setPermissionsTeamId(2);
    
    $response = getJson("/api/admin/acl/roles/{$role->id}");
    
    $response->assertStatus(404);
});

it('syncs permissions per tenant', function() {
    setPermissionsTeamId(1);
    $perm1 = Permission::create(['name' => 'posts.create', 'group' => 'posts']);
    
    setPermissionsTeamId(2);
    $perm2 = Permission::create(['name' => 'posts.create', 'group' => 'posts']);
    
    expect($perm1->id)->not->toBe($perm2->id);
});
```

---

#### Test: Localization

**File:** `tests/Feature/LocalizationTest.php`

```php
<?php

use Spatie\Permission\Models\{Role, Permission};
use function Pest\Laravel\{postJson, getJson};

beforeEach(function() {
    config(['roles.i18n.enabled' => true]);
    config(['roles.i18n.locales' => ['en', 'ar']]);
    
    $this->user = $this->authenticatedUser(['roles.create', 'permissions.create']);
});

it('stores multilingual labels', function() {
    $response = postJson('/api/admin/acl/roles', [
        'name' => 'editor',
        'label' => [
            'en' => 'Content Editor',
            'ar' => 'محرر المحتوى',
        ],
        'guard_name' => 'web',
    ]);
    
    $response->assertStatus(201);
    
    $role = Role::where('name', 'editor')->first();
    expect($role->label)->toBe([
        'en' => 'Content Editor',
        'ar' => 'محرر المحتوى',
    ]);
});

it('returns localized labels based on Accept-Language header', function() {
    $role = Role::create([
        'name' => 'editor',
        'label' => [
            'en' => 'Content Editor',
            'ar' => 'محرر المحتوى',
        ],
    ]);
    
    $response = getJson('/api/admin/acl/roles', [
        'Accept-Language' => 'ar',
    ]);
    
    $response->assertStatus(200);
    // Verify Arabic label is returned
});

it('falls back to default locale when translation missing', function() {
    $role = Role::create([
        'name' => 'editor',
        'label' => ['en' => 'Content Editor'],
    ]);
    
    $response = getJson('/api/admin/acl/roles', [
        'Accept-Language' => 'ar',
    ]);
    
    $response->assertStatus(200);
    // Should fall back to English
});
```

---

#### Test: API Guards

**File:** `tests/Feature/ApiGuardTest.php`

```php
<?php

use Spatie\Permission\Models\Role;
use function Pest\Laravel\{getJson, postJson};

it('requires authentication for all endpoints', function() {
    $response = getJson('/api/admin/acl/roles');
    
    $response->assertStatus(401);
});

it('works with web guard', function() {
    config(['roles.guard' => 'web']);
    
    $user = $this->authenticatedUser(['roles.list']);
    
    $response = getJson('/api/admin/acl/roles');
    
    $response->assertStatus(200);
});

it('works with api guard', function() {
    config(['roles.guard' => 'api']);
    
    $user = $this->authenticatedUser(['roles.list']);
    
    $response = getJson('/api/admin/acl/roles');
    
    $response->assertStatus(200);
});

it('enforces permission-based authorization', function() {
    $user = $this->authenticatedUser(); // No permissions
    
    $response = postJson('/api/admin/acl/roles', [
        'name' => 'test-role',
    ]);
    
    $response->assertStatus(403);
});
```

---

### 3. UNIT TESTS

**File:** `tests/Unit/TenancyAdapterTest.php`

```php
<?php

use Enadstack\LaravelRoles\Support\TenancyAdapters\{
    NullTenancyAdapter,
    StanclTenancyAdapter,
    SpatieTenancyAdapter
};

it('NullTenancyAdapter returns null for tenant ID', function() {
    $adapter = new NullTenancyAdapter();
    
    expect($adapter->getCurrentTenantId())->toBeNull();
    expect($adapter->isActive())->toBeFalse();
});

it('NullTenancyAdapter does not apply scope', function() {
    $adapter = new NullTenancyAdapter();
    $query = \Spatie\Permission\Models\Role::query();
    
    $adapter->applyScope($query);
    
    // Query should remain unchanged
    expect($query->toSql())->not->toContain('team_id');
});
```

---

### 4. CI/CD CONFIGURATION

**File:** `.github/workflows/tests.yml`

```yaml
name: Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php: [8.2, 8.3]
        laravel: [12.x]
        stability: [prefer-stable]
    
    name: PHP ${{ matrix.php }} - Laravel ${{ matrix.laravel }}
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite
          coverage: xdebug
      
      - name: Install dependencies
        run: |
          composer require "laravel/framework:${{ matrix.laravel }}" --no-interaction --no-update
          composer update --${{ matrix.stability }} --prefer-dist --no-interaction
      
      - name: Execute tests
        run: vendor/bin/pest --coverage --min=80
      
      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
          fail_ci_if_error: true
```

---

### 5. TEST COVERAGE GOALS

| Category | Target Coverage | Priority |
|----------|----------------|----------|
| Controllers | 100% | P0 |
| Services | 100% | P0 |
| Repositories | 100% | P0 |
| Requests | 100% | P0 |
| Middleware | 90% | P1 |
| Events | 80% | P1 |
| Policies | 90% | P1 |
| Overall | ≥ 80% | P0 |

---

### 6. RUNNING TESTS

```bash
# Run all tests
vendor/bin/pest

# Run with coverage
vendor/bin/pest --coverage --min=80

# Run specific test file
vendor/bin/pest tests/Feature/RoleCrudTest.php

# Run specific test
vendor/bin/pest --filter "creates a role with valid data"

# Run tests in parallel
vendor/bin/pest --parallel

# Generate HTML coverage report
vendor/bin/pest --coverage-html coverage
```

---

**Testing Guide Version:** 1.0  
**Last Updated:** 2025-12-19

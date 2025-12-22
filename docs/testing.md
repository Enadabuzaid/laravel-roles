# Testing

This document covers the testing strategy and how to run tests.

## Testing Philosophy

The package follows these testing principles:

1. **No external dependencies**: Tests do not require real tenancy packages
2. **Config permutations**: Core tests run under all guard/tenancy combinations
3. **No test pollution**: Each test resets config, cache, and database
4. **Structure over snapshots**: Assert data structure, not exact strings
5. **CI-ready**: Tests run in automated pipelines

## Test Structure

```
tests/
├── TestCase.php              # Base test case
├── database/
│   └── migrations/           # Test-specific migrations
├── Traits/
│   ├── UsesSingleTenancy.php
│   ├── UsesTeamScopedTenancy.php
│   ├── UsesMultiDatabaseTenancy.php
│   ├── UsesWebGuard.php
│   ├── UsesApiGuard.php
│   └── SeedsRolesAndPermissions.php
├── Unit/
│   ├── TenantContextTest.php
│   ├── GuardResolverTest.php
│   ├── CacheKeyBuilderTest.php
│   ├── WildcardExpansionTest.php
│   └── DiffSyncTest.php
└── Feature/
    ├── RolesCrudTest.php
    ├── PermissionMatrixTest.php
    ├── DiffPermissionTest.php
    ├── SyncCommandTest.php
    ├── UIRoutesTest.php
    ├── ConfigurationMatrixTest.php
    ├── PerformanceAndSafetyTest.php
    └── UpgradeSafetyTest.php
```

## Running Tests Locally

### Using PHPUnit

```bash
# All tests
./vendor/bin/phpunit

# Unit tests only
./vendor/bin/phpunit --testsuite=Unit

# Feature tests only
./vendor/bin/phpunit --testsuite=Feature
```

### Using Pest

```bash
# All tests
./vendor/bin/pest

# Specific file
./vendor/bin/pest tests/Feature/RolesCrudTest.php
```

### Using Composer Scripts

```bash
# All tests
composer test

# Unit only
composer test:unit

# Feature only
composer test:feature

# With coverage
composer test:coverage
```

## Test Traits

### UsesSingleTenancy

```php
use Tests\Traits\UsesSingleTenancy;

class MyTest extends TestCase
{
    use UsesSingleTenancy;

    public function test_something()
    {
        $this->setUpSingleTenancy();
        // Tenancy mode is now 'single'
    }
}
```

### UsesTeamScopedTenancy

```php
use Tests\Traits\UsesTeamScopedTenancy;

class MyTest extends TestCase
{
    use UsesTeamScopedTenancy;

    public function test_team_isolation()
    {
        $this->setUpTeamScopedTenancy(1);

        // Create data for team 1
        Role::create(['name' => 'team1-role', 'guard_name' => 'web']);

        // Switch to team 2
        $this->switchToTeam(2);

        // Team 2 should not see team 1 data
    }
}
```

### UsesMultiDatabaseTenancy

```php
use Tests\Traits\UsesMultiDatabaseTenancy;

class MyTest extends TestCase
{
    use UsesMultiDatabaseTenancy;

    public function test_multi_db()
    {
        $this->setUpMultiDatabaseTenancy('tenant_a');

        // Run test in tenant_a context

        $this->switchToTenant('tenant_b');
        // Now in tenant_b context
    }
}
```

### SeedsRolesAndPermissions

```php
use Tests\Traits\SeedsRolesAndPermissions;

class MyTest extends TestCase
{
    use SeedsRolesAndPermissions;

    public function test_with_data()
    {
        $this->seedDefaultRoles();          // admin, editor, viewer
        $this->seedDefaultPermissions();    // 15 permissions
        $this->seedRolesWithPermissions();  // Both with assignments
    }
}
```

## Configuration Permutations

The `ConfigurationMatrixTest` runs core tests under all combinations:

| Guard | Tenancy Mode |
|-------|--------------|
| web | single |
| web | team_scoped |
| web | multi_database |
| api | single |
| api | team_scoped |
| api | multi_database |

Each combination tests:

- Role CRUD
- Permission sync
- Matrix endpoint
- Guard correctness

## Performance Tests

Tests include query count assertions:

```php
public function test_matrix_is_efficient()
{
    $this->seedRolesWithPermissions();

    $this->assertNoN1(function () {
        $this->getJson('/admin/acl/matrix');
    }, 5, 'Matrix should use at most 5 queries');
}
```

The `assertNoN1()` helper:

1. Starts counting queries
2. Runs the callback
3. Asserts count is within limit

## Cache Tests

Tests verify cache invalidation:

```php
public function test_cache_invalidated_on_role_create()
{
    // Populate cache
    $this->getJson('/admin/acl/roles');

    // Create new role
    $this->postJson('/admin/acl/roles', [...]);

    // Cache should include new role
    $response = $this->getJson('/admin/acl/roles');
    // Assert new role is in response
}
```

## Writing New Tests

### Base Test Case

Always extend the package's TestCase:

```php
namespace Tests\Feature;

use Tests\TestCase;

class MyNewTest extends TestCase
{
    public function test_something()
    {
        // Test code
    }
}
```

### Setting Up Test Data

```php
protected function setUp(): void
{
    parent::setUp();

    // Use traits for setup
    $this->setUpSingleTenancy();
    $this->seedDefaultRoles();
}
```

### Asserting API Responses

```php
public function test_api_structure()
{
    $response = $this->getJson('/admin/acl/roles');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'name', 'guard_name'],
        ],
        'meta' => ['current_page', 'total'],
    ]);
}
```

## CI Configuration

Tests run in GitHub Actions or similar:

```yaml
# .github/workflows/tests.yml
- name: Run Tests
  run: ./vendor/bin/phpunit --coverage-text
```

## Test Coverage

Generate coverage report:

```bash
composer test:coverage
```

Coverage is output to `build/coverage/`.

## Next Steps

- [Configuration](configuration.md)
- [Troubleshooting](troubleshooting.md)

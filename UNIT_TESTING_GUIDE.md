# Unit Testing Guide - Laravel Roles Package

## Overview

This package includes comprehensive unit tests for both RoleService and PermissionService, ensuring reliability and preventing regressions.

---

## 📦 Test Files

### 1. RoleServiceTest
**Location:** `tests/Unit/RoleServiceTest.php`

**Coverage:**
- List and pagination (20+ tests)
- Filtering (search, status, deleted records)
- CRUD operations (create, update, delete, restore)
- Status management (activate, deactivate, bulk change)
- Statistics and analytics
- Permission assignment
- Role cloning
- Bulk operations

### 2. PermissionServiceTest
**Location:** `tests/Unit/PermissionServiceTest.php`

**Coverage:**
- List and pagination (20+ tests)
- Filtering (search, status, group, deleted records)
- CRUD operations (create, update, delete, restore)
- Status management (activate, deactivate, bulk change)
- Statistics and analytics
- Grouped permissions
- Permission matrix
- Bulk operations

---

## 🚀 Running Tests

### Run All Tests
```bash
./vendor/bin/pest
```

### Run Specific Test File
```bash
# RoleService tests only
./vendor/bin/pest tests/Unit/RoleServiceTest.php

# PermissionService tests only
./vendor/bin/pest tests/Unit/PermissionServiceTest.php
```

### Run Specific Test
```bash
./vendor/bin/pest --filter=it_can_filter_only_deleted_roles
```

### Run with Coverage
```bash
./vendor/bin/pest --coverage
```

### Run with Verbose Output
```bash
./vendor/bin/pest --verbose
```

---

## 📋 Test Examples

### RoleService Tests

#### Test Filtering by Status
```php
/** @test */
public function it_can_filter_roles_by_status()
{
    Role::create(['name' => 'admin', 'guard_name' => 'web', 'status' => 'active']);
    Role::create(['name' => 'editor', 'guard_name' => 'web', 'status' => 'inactive']);
    Role::create(['name' => 'viewer', 'guard_name' => 'web', 'status' => 'active']);

    $result = $this->roleService->list(['status' => 'active'], 10);

    $this->assertCount(2, $result->items());
}
```

#### Test Only Deleted Filter
```php
/** @test */
public function it_can_filter_only_deleted_roles()
{
    $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    Role::create(['name' => 'viewer', 'guard_name' => 'web']);

    $role1->delete();
    $role2->delete();

    $result = $this->roleService->list(['only_deleted' => true], 10);

    $this->assertCount(2, $result->items());
}
```

#### Test Status Change
```php
/** @test */
public function it_can_change_role_status()
{
    $role = Role::create(['name' => 'admin', 'guard_name' => 'web', 'status' => 'active']);

    $updated = $this->roleService->changeStatus($role, RolePermissionStatusEnum::INACTIVE);

    $this->assertEquals('inactive', $updated->status);
}
```

#### Test Bulk Operations
```php
/** @test */
public function it_can_bulk_change_status()
{
    $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $role3 = Role::create(['name' => 'viewer', 'guard_name' => 'web']);

    $results = $this->roleService->bulkChangeStatus(
        [$role1->id, $role2->id, $role3->id],
        RolePermissionStatusEnum::INACTIVE
    );

    $this->assertCount(3, $results['success']);
    $this->assertCount(0, $results['failed']);
}
```

### PermissionService Tests

#### Test Filtering by Group
```php
/** @test */
public function it_can_filter_permissions_by_group()
{
    Permission::create(['name' => 'posts.edit', 'guard_name' => 'web', 'group' => 'posts']);
    Permission::create(['name' => 'posts.delete', 'guard_name' => 'web', 'group' => 'posts']);
    Permission::create(['name' => 'users.view', 'guard_name' => 'web', 'group' => 'users']);

    $result = $this->permissionService->list(['group' => 'posts', 'guard' => 'web'], 10);

    $this->assertCount(2, $result->items());
}
```

#### Test With Deleted Filter
```php
/** @test */
public function it_can_filter_with_deleted_permissions()
{
    $perm1 = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
    Permission::create(['name' => 'delete-posts', 'guard_name' => 'web']);
    Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

    $perm1->delete();

    $result = $this->permissionService->list(['with_deleted' => true, 'guard' => 'web'], 10);

    $this->assertCount(3, $result->items());
}
```

---

## 🎯 Test Coverage

### RoleServiceTest Coverage

| Feature | Tests |
|---------|-------|
| List & Pagination | 3 |
| Filtering | 5 |
| CRUD Operations | 5 |
| Status Management | 4 |
| Bulk Operations | 3 |
| Statistics | 1 |
| Permissions | 2 |
| Other | 2 |
| **Total** | **25+** |

### PermissionServiceTest Coverage

| Feature | Tests |
|---------|-------|
| List & Pagination | 3 |
| Filtering | 6 |
| CRUD Operations | 5 |
| Status Management | 4 |
| Bulk Operations | 3 |
| Statistics | 2 |
| Utilities | 3 |
| **Total** | **26+** |

---

## ✅ What's Tested

### Filtering
- ✅ Search filtering
- ✅ Status filtering (active, inactive, deleted)
- ✅ Group filtering (permissions only)
- ✅ Guard filtering
- ✅ `only_deleted` filter - Only soft-deleted records
- ✅ `with_deleted` filter - Both active and deleted records
- ✅ Default behavior - Only non-deleted records

### CRUD Operations
- ✅ Create with default status
- ✅ Update
- ✅ Soft delete (status changes to 'deleted')
- ✅ Restore (status changes to 'active')
- ✅ Force delete

### Status Management
- ✅ Change status to any value
- ✅ Activate (change to 'active')
- ✅ Deactivate (change to 'inactive')
- ✅ Bulk status change
- ✅ Observer automatic status changes

### Statistics
- ✅ Total count
- ✅ Active count
- ✅ Inactive count
- ✅ Deleted count
- ✅ Status breakdown (`by_status`)
- ✅ Growth statistics
- ✅ Permissions assigned/unassigned

### Bulk Operations
- ✅ Bulk delete
- ✅ Bulk restore
- ✅ Bulk force delete
- ✅ Bulk status change

---

## 🧪 Writing New Tests

### Test Template
```php
/** @test */
public function it_can_do_something()
{
    // Arrange
    $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    
    // Act
    $result = $this->roleService->doSomething($role);
    
    // Assert
    $this->assertEquals('expected', $result);
}
```

### Best Practices

1. **Use Descriptive Names**
   ```php
   // Good
   public function it_can_filter_only_deleted_roles()
   
   // Bad
   public function test_filter()
   ```

2. **Follow AAA Pattern**
   - Arrange: Set up test data
   - Act: Execute the code
   - Assert: Verify the results

3. **Test One Thing**
   - Each test should verify one specific behavior
   - Don't mix multiple assertions for different features

4. **Use Factories When Possible**
   ```php
   Role::factory()->count(5)->create();
   ```

5. **Clean Up**
   - Use `RefreshDatabase` trait
   - Database is reset between tests

---

## 🔍 Debugging Tests

### Run Single Test
```bash
./vendor/bin/pest --filter=it_can_filter_only_deleted_roles
```

### Show Full Error Output
```bash
./vendor/bin/pest --verbose
```

### Stop on First Failure
```bash
./vendor/bin/pest --stop-on-failure
```

### Debug with dd()
```php
/** @test */
public function it_can_do_something()
{
    $result = $this->roleService->list(['status' => 'active']);
    
    dd($result); // Dump and die
    
    $this->assertCount(1, $result);
}
```

---

## 📊 CI/CD Integration

### GitHub Actions Example
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          
      - name: Install Dependencies
        run: composer install
        
      - name: Run Tests
        run: ./vendor/bin/pest
```

---

## 🎯 Testing Checklist

When adding new features, make sure to test:

- [ ] CRUD operations work correctly
- [ ] Filters work as expected
- [ ] Status management functions properly
- [ ] Observers update status automatically
- [ ] Bulk operations handle errors gracefully
- [ ] Statistics are accurate
- [ ] Edge cases are covered
- [ ] Backward compatibility is maintained

---

## 📚 Additional Resources

- **Pest Documentation:** https://pestphp.com
- **Laravel Testing:** https://laravel.com/docs/testing
- **PHPUnit Documentation:** https://phpunit.de

---

## 🎊 Summary

The Laravel Roles package now includes comprehensive unit tests ensuring:

- ✅ All service methods are tested
- ✅ Filters work correctly (`only_deleted`, `with_deleted`, `status`)
- ✅ Status management is reliable
- ✅ Bulk operations are safe
- ✅ Statistics are accurate
- ✅ Observers function properly

**Total Tests:** 50+ comprehensive unit tests  
**Coverage:** All major service functionality  
**Framework:** Pest PHP (Laravel optimized)


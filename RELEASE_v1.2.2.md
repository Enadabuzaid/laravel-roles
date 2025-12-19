# Release Notes - Version 1.2.2

**Release Date:** December 19, 2025

## 🎉 What's New

### Status Management System
Complete status management implementation for both roles and permissions with automatic status handling through observers.

#### ✨ New Features

**1. RolePermissionStatusEnum**
- New enum with three statuses: `active`, `inactive`, `deleted`
- Helper methods: `isActive()`, `isInactive()`, `isDeleted()`
- UI helpers: `label()`, `color()`, `badge()`
- Static helper: `values()` to get all possible values

**2. Database Migration**
- Added `status` column to `roles` table
- Added `status` column to `permissions` table
- Indexed for performance
- Automatic migration of existing data

**3. Model Observers**
- **RoleObserver**: Automatically manages role status
  - Sets `active` status on creation
  - Sets `deleted` status on soft delete
  - Restores to `active` status on restore
- **PermissionObserver**: Automatically manages permission status
  - Sets `active` status on creation
  - Sets `deleted` status on soft delete
  - Restores to `active` status on restore

**4. Service Methods**

**RoleService new methods:**
- `changeStatus(Role $role, RolePermissionStatusEnum $status)` - Change role status
- `activate(Role $role)` - Activate a role
- `deactivate(Role $role)` - Deactivate a role
- `bulkChangeStatus(array $ids, RolePermissionStatusEnum $status)` - Bulk status change
- `getStatsByStatus()` - Get statistics grouped by status

**PermissionService new methods:**
- `changeStatus(Permission $permission, RolePermissionStatusEnum $status)` - Change permission status
- `activate(Permission $permission)` - Activate a permission
- `deactivate(Permission $permission)` - Deactivate a permission
- `bulkChangeStatus(array $ids, RolePermissionStatusEnum $status)` - Bulk status change
- `getStatsByStatus()` - Get statistics grouped by status

**5. Controller Endpoints**

**Role endpoints:**
- `PATCH /admin/acl/roles/{role}/status` - Change role status
- `POST /admin/acl/roles/{role}/activate` - Activate role
- `POST /admin/acl/roles/{role}/deactivate` - Deactivate role
- `POST /admin/acl/roles/bulk-change-status` - Bulk status change

**Permission endpoints:**
- `PATCH /admin/acl/permissions/{permission}/status` - Change permission status
- `POST /admin/acl/permissions/{permission}/activate` - Activate permission
- `POST /admin/acl/permissions/{permission}/deactivate` - Deactivate permission
- `POST /admin/acl/permissions/bulk-change-status` - Bulk status change

**6. Enhanced Statistics**
- Role stats now include: `active`, `inactive`, `deleted`, `by_status`
- Permission stats now include: `active`, `inactive`, `deleted`, `by_status`
- Growth statistics continue to track all status changes

**7. Enhanced Filtering**
- Added `only_deleted` filter - Show only soft-deleted records
- Added `with_deleted` filter - Show both active and soft-deleted records  
- Added status-based filtering: `?status=active|inactive|deleted`
- Added status to allowed sort fields
- Backward compatibility: `only_trashed` and `with_trashed` still work

**8. Unit Tests**
- **RoleServiceTest** - Comprehensive unit tests for RoleService (20+ tests)
- **PermissionServiceTest** - Comprehensive unit tests for PermissionService (20+ tests)
- Full test coverage for all service methods
- Tests for filtering, CRUD, status management, bulk operations

---

## 📁 New Files

### Core Files
1. `src/Enums/RolePermissionStatusEnum.php` - Status enum definition
2. `src/Observers/RoleObserver.php` - Role model observer
3. `src/Observers/PermissionObserver.php` - Permission model observer
4. `database/migrations/2025_12_19_000000_add_status_to_roles_and_permissions.php` - Migration

### Test Files
5. `tests/Unit/RoleServiceTest.php` - RoleService unit tests
6. `tests/Unit/PermissionServiceTest.php` - PermissionService unit tests

---

## 🔧 Modified Files

1. **src/Services/RoleService.php**
   - Added status filtering
   - Added status management methods
   - Updated stats to include status-based counts

2. **src/Services/PermissionService.php**
   - Added status filtering
   - Added status management methods
   - Updated stats to include status-based counts

3. **src/Http/Controllers/RoleController.php**
   - Added 4 new status management endpoints
   - Updated to use RolePermissionStatusEnum

4. **src/Http/Controllers/PermissionController.php**
   - Added 4 new status management endpoints
   - Updated to use RolePermissionStatusEnum

5. **src/Models/Role.php**
   - Added `status` to fillable fields

6. **src/Models/Permission.php**
   - Added `status` to fillable fields

7. **src/Providers/RolesServiceProvider.php**
   - Registered RoleObserver
   - Registered PermissionObserver

8. **routes/roles.php**
   - Added 8 new status management routes

9. **composer.json**
   - Version updated to 1.2.2

---

## 📊 API Examples

### Change Role Status
```bash
PATCH /admin/acl/roles/1/status
{
  "status": "inactive"
}

Response:
{
  "success": true,
  "message": "Role status updated successfully",
  "data": {
    "id": 1,
    "name": "admin",
    "status": "inactive",
    ...
  }
}
```

### Activate Role
```bash
POST /admin/acl/roles/1/activate

Response:
{
  "success": true,
  "message": "Role activated successfully",
  "data": {
    "id": 1,
    "name": "admin",
    "status": "active",
    ...
  }
}
```

### Bulk Change Status
```bash
POST /admin/acl/roles/bulk-change-status
{
  "ids": [1, 2, 3],
  "status": "inactive"
}

Response:
{
  "success": true,
  "message": "Bulk status change completed",
  "data": {
    "success": [1, 2, 3],
    "failed": []
  }
}
```

### Get Stats with Status
```bash
GET /admin/acl/roles-stats

Response:
{
  "success": true,
  "data": {
    "total": 15,
    "active": 12,
    "inactive": 2,
    "deleted": 1,
    "with_permissions": 10,
    "without_permissions": 5,
    "by_status": {
      "active": 12,
      "inactive": 2,
      "deleted": 1
    },
    "growth": { ... }
  }
}
```

### Filter by Status
```bash
GET /admin/acl/roles?status=active
GET /admin/acl/permissions?status=inactive
```

### Filter Deleted Records
```bash
# Show only deleted records
GET /admin/acl/roles?only_deleted=true

# Show both active and deleted records
GET /admin/acl/roles?with_deleted=true

# Default: show only non-deleted records
GET /admin/acl/roles
```

---

## 🎯 Automatic Status Management

### On Create
```php
$role = Role::create(['name' => 'editor']);
// Observer automatically sets: status = 'active'
```

### On Soft Delete
```php
$role->delete();
// Observer automatically sets: status = 'deleted'
```

### On Restore
```php
$role->restore();
// Observer automatically sets: status = 'active'
```

### Manual Status Change
```php
use Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum;

$roleService->changeStatus($role, RolePermissionStatusEnum::INACTIVE);
$roleService->activate($role);
$roleService->deactivate($role);
```

---

## 💡 Use Cases

### 1. Temporarily Disable a Role
```php
// Deactivate role without deleting it
$roleService->deactivate($role);
// Users with this role will have inactive role
```

### 2. Bulk Status Management
```php
// Deactivate multiple roles at once
$roleService->bulkChangeStatus([1, 2, 3], RolePermissionStatusEnum::INACTIVE);
```

### 3. Filter Active Roles
```php
// Get only active roles
$activeRoles = $roleService->list(['status' => 'active']);
```

### 4. Statistics by Status
```php
$stats = $roleService->stats();
echo "Active roles: {$stats['active']}";
echo "Inactive roles: {$stats['inactive']}";
echo "Deleted roles: {$stats['deleted']}";
```

---

## 🔄 Migration Guide

### From Version 1.2.1

```bash
# Update package
composer update enadstack/laravel-roles

# Run migration
php artisan migrate

# Existing roles and permissions will automatically get 'active' status
# Soft-deleted items will get 'deleted' status
```

### Updating Your Code

**Before:**
```php
// No status management available
```

**After:**
```php
use Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum;

// Change status
$roleService->changeStatus($role, RolePermissionStatusEnum::INACTIVE);

// Or use helpers
$roleService->activate($role);
$roleService->deactivate($role);

// Filter by status
$activeRoles = $roleService->list(['status' => 'active']);
```

---

## 📚 Enum Helper Methods

```php
use Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum;

// Get all values
$statuses = RolePermissionStatusEnum::values();
// ['active', 'inactive', 'deleted']

// Get label
$status = RolePermissionStatusEnum::ACTIVE;
echo $status->label(); // 'Active'

// Get color (for UI)
echo $status->color(); // 'success'

// Get HTML badge
echo $status->badge(); // '<span class="badge badge-success">Active</span>'

// Check status
if ($status->isActive()) {
    // ...
}
```

---

## ⚠️ Breaking Changes

**None** - This release is fully backward compatible.

Existing roles and permissions without a status column will automatically receive the `active` status during migration.

---

## 🧪 Testing

### Test Observer Behavior
```php
public function test_role_status_changes_on_delete()
{
    $role = Role::create(['name' => 'test-role']);
    
    $this->assertEquals('active', $role->status);
    
    $role->delete();
    $this->assertEquals('deleted', $role->fresh()->status);
    
    $role->restore();
    $this->assertEquals('active', $role->fresh()->status);
}
```

### Test Status Filtering
```php
public function test_can_filter_roles_by_status()
{
    Role::factory()->create(['status' => 'active']);
    Role::factory()->create(['status' => 'inactive']);
    
    $response = $this->getJson('/api/roles?status=active');
    
    $response->assertOk()
        ->assertJsonCount(1, 'data');
}
```

---

## 🧪 Testing

### Run Unit Tests
```bash
# Run all tests
./vendor/bin/pest

# Run only RoleService tests
./vendor/bin/pest tests/Unit/RoleServiceTest.php

# Run only PermissionService tests
./vendor/bin/pest tests/Unit/PermissionServiceTest.php

# Run with coverage
./vendor/bin/pest --coverage
```

### Test Observer Behavior
```php
public function test_role_status_changes_on_delete()
{
    $role = Role::create(['name' => 'test-role']);
    
    $this->assertEquals('active', $role->status);
    
    $role->delete();
    $this->assertEquals('deleted', $role->fresh()->status);
    
    $role->restore();
    $this->assertEquals('active', $role->fresh()->status);
}
```

### Test Status Filtering
```php
public function test_can_filter_roles_by_status()
{
    Role::factory()->create(['status' => 'active']);
    Role::factory()->create(['status' => 'inactive']);
    
    $response = $this->getJson('/api/roles?status=active');
    
    $response->assertOk()
        ->assertJsonCount(1, 'data');
}
```

### Test Only Deleted Filter
```php
public function test_can_filter_only_deleted()
{
    $role1 = Role::create(['name' => 'admin']);
    $role2 = Role::create(['name' => 'editor']);
    
    $role1->delete();
    
    $result = $roleService->list(['only_deleted' => true]);
    
    $this->assertCount(1, $result->items());
}
```

---

## 📖 Documentation

- **Enum Reference**: See `src/Enums/RolePermissionStatusEnum.php`
- **Observer Reference**: See `src/Observers/RoleObserver.php` and `PermissionObserver.php`
- **Migration**: See `database/migrations/2025_12_19_000000_add_status_to_roles_and_permissions.php`

---

## 🎊 Summary

Version 1.2.2 adds comprehensive status management to the Laravel Roles package:

- ✅ Three status levels: active, inactive, deleted
- ✅ Automatic status management through observers
- ✅ Manual status change endpoints
- ✅ Bulk operations support
- ✅ Enhanced statistics
- ✅ Status-based filtering
- ✅ Fully backward compatible

**Version:** 1.2.2  
**Previous Version:** 1.2.1  
**Release Type:** Minor Release (New Features)  
**Status:** ✅ Production Ready


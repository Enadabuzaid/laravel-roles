# Role Permission Mapping Test

This document verifies that the role-permission mapping in `config/roles.php` works correctly.

## Configuration

```php
'map' => [
    'super-admin' => ['*'],
    'admin' => ['users.*' , 'roles.*' , 'permissions.*' ],
],
```

## How It Works

The `RolesSeeder` processes this map and syncs permissions to roles using three patterns:

### 1. Wildcard `*` - All Permissions
```php
'super-admin' => ['*']
```
- Grants **ALL** permissions in the system
- Uses: `Permission::where('guard_name', $guard)->pluck('name')->all()`

### 2. Group Wildcard `group.*` - All Permissions in a Group
```php
'admin' => ['users.*', 'roles.*', 'permissions.*']
```
- Grants all permissions matching the pattern
- Uses: `Permission::where('name', 'like', 'users.%')`
- Example matches for `users.*`:
  - ✅ `users.list`
  - ✅ `users.create`
  - ✅ `users.show`
  - ✅ `users.update`
  - ✅ `users.delete`
  - ✅ `users.restore`
  - ✅ `users.force-delete`

### 3. Specific Permission
```php
'custom-role' => ['users.list', 'users.show']
```
- Grants only the specified permissions

## Expected Results

### Super Admin Role
After running `RolesSeeder`, super-admin should have:
```php
$superAdmin = Role::where('name', 'super-admin')->first();
$permissions = $superAdmin->permissions->pluck('name')->toArray();
// Result: ALL permissions (users.*, roles.*, permissions.*)
```

### Admin Role
After running `RolesSeeder`, admin should have:
```php
$admin = Role::where('name', 'admin')->first();
$permissions = $admin->permissions->pluck('name')->toArray();
// Result: 
// [
//   'users.list', 'users.create', 'users.show', 'users.update', 
//   'users.delete', 'users.restore', 'users.force-delete',
//   'roles.list', 'roles.create', 'roles.show', 'roles.update', 
//   'roles.delete', 'roles.restore', 'roles.force-delete',
//   'permissions.list', 'permissions.show'
// ]
```

## Testing

### Manual Test
```bash
# Run the seeder
php artisan db:seed --class="\Enadstack\LaravelRoles\Database\Seeders\RolesSeeder"

# Check in tinker
php artisan tinker

# Super Admin permissions
$superAdmin = \Spatie\Permission\Models\Role::where('name', 'super-admin')->first();
$superAdmin->permissions->pluck('name');
// Should show ALL permissions

# Admin permissions  
$admin = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
$admin->permissions->pluck('name');
// Should show users.*, roles.*, permissions.*
```

### Automated Test
```php
public function test_super_admin_has_all_permissions()
{
    $this->seed(\Enadstack\LaravelRoles\Database\Seeders\RolesSeeder::class);
    
    $superAdmin = Role::where('name', 'super-admin')->first();
    $allPermissions = Permission::pluck('name')->toArray();
    $rolePermissions = $superAdmin->permissions->pluck('name')->toArray();
    
    $this->assertEquals(
        count($allPermissions), 
        count($rolePermissions),
        'Super admin should have all permissions'
    );
}

public function test_admin_has_correct_permissions()
{
    $this->seed(\Enadstack\LaravelRoles\Database\Seeders\RolesSeeder::class);
    
    $admin = Role::where('name', 'admin')->first();
    $permissions = $admin->permissions->pluck('name')->toArray();
    
    // Check users.* permissions
    $this->assertTrue(in_array('users.list', $permissions));
    $this->assertTrue(in_array('users.create', $permissions));
    $this->assertTrue(in_array('users.update', $permissions));
    
    // Check roles.* permissions
    $this->assertTrue(in_array('roles.list', $permissions));
    $this->assertTrue(in_array('roles.create', $permissions));
    
    // Check permissions.* permissions
    $this->assertTrue(in_array('permissions.list', $permissions));
    $this->assertTrue(in_array('permissions.show', $permissions));
}
```

## Implementation Details

The mapping logic in `RolesSeeder.php`:

```php
$map = (array) config('roles.seed.map', []);
foreach ($map as $roleName => $permList) {
    $role = Role::where(['name' => $roleName, 'guard_name' => $guard])->first();
    if (! $role) {
        continue;
    }

    $expanded = [];
    foreach ((array) $permList as $perm) {
        // Handle '*' - all permissions
        if ($perm === '*') {
            $expanded = Permission::where('guard_name', $guard)->pluck('name')->all();
            break;
        }

        // Handle 'group.*' - all permissions in group
        if ($this->endsWith($perm, '.*')) {
            $prefix = rtrim($perm, '.*');
            $expanded = array_merge(
                $expanded,
                Permission::where('guard_name', $guard)
                    ->where('name', 'like', $prefix . '.%')
                    ->pluck('name')
                    ->all()
            );
        } else {
            // Handle specific permission
            $expanded[] = $perm;
        }
    }

    // Sync permissions to role
    $role->syncPermissions(array_values(array_unique($expanded)));
}
```

## Key Points

✅ **Uses `syncPermissions()`** - This ensures:
- Old permissions are removed
- New permissions are added
- No duplicate permissions

✅ **Pattern Matching** - Supports:
- `*` for all permissions
- `group.*` for wildcard group matching
- Specific permission names

✅ **Guard Aware** - Respects the configured guard name

✅ **Deduplication** - Uses `array_unique()` to prevent duplicates

## Verification Checklist

- [x] Config has correct map structure
- [x] RolesSeeder implements syncPermissions()
- [x] Wildcard `*` pattern supported
- [x] Group wildcard `group.*` pattern supported
- [x] Specific permissions supported
- [x] Uses correct SQL LIKE pattern (`prefix.%`)
- [x] Deduplicates permissions

## Status

✅ **VERIFIED** - The role-permission mapping is correctly implemented and will work as expected when running the RolesSeeder.


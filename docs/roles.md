# Roles

This document covers role management in Laravel Roles.

## Role Model

The package extends Spatie's Role model with additional features:

```php
use Enadstack\LaravelRoles\Models\Role;

$role = Role::create([
    'name' => 'editor',
    'guard_name' => 'web',
    'description' => 'Can edit content',
    'status' => 'active',
]);
```

### Additional Fields

| Field | Type | Description |
|-------|------|-------------|
| `description` | text/json | Role description (JSON if i18n enabled) |
| `label` | json | Translatable display name (i18n only) |
| `status` | string | `active` or `inactive` |
| `deleted_at` | timestamp | Soft delete timestamp |

## CRUD Lifecycle

### Creating Roles

```php
// Via service
$roleService = app(RoleServiceContract::class);
$role = $roleService->create([
    'name' => 'moderator',
    'guard_name' => 'web',
    'description' => 'Moderates user content',
]);

// Via API
POST /admin/acl/roles
{
    "name": "moderator",
    "guard_name": "web",
    "description": "Moderates user content"
}
```

### Updating Roles

```php
$roleService->update($role, [
    'description' => 'Updated description',
]);

// Via API
PUT /admin/acl/roles/{id}
{
    "description": "Updated description"
}
```

### Deleting Roles (Soft Delete)

```php
$roleService->delete($role);

// Via API
DELETE /admin/acl/roles/{id}
```

### Restoring Roles

```php
$roleService->restore($role);

// Via API
POST /admin/acl/roles/{id}/restore
```

### Force Deleting Roles

```php
$roleService->forceDelete($role);

// Via API
DELETE /admin/acl/roles/{id}/force
```

## Soft Deletes

Roles support soft deletes by default. Deleted roles:

- Are not visible in normal queries
- Can be restored
- Retain their permission assignments
- Can be force deleted permanently

### Querying Deleted Roles

```php
// Include deleted
Role::withTrashed()->get();

// Only deleted
Role::onlyTrashed()->get();

// Via API
GET /admin/acl/roles?with_trashed=true
GET /admin/acl/roles?only_trashed=true
```

## Tenant Isolation

In multi-tenant modes, roles are isolated:

### Team-Scoped Mode

Roles have a `team_id` column:

```php
// Roles are automatically scoped to current team
$roles = Role::all(); // Only current team's roles

// Explicitly query other teams (not recommended)
Role::where('team_id', $otherTeamId)->get();
```

### Multi-Database Mode

Each tenant database has its own `roles` table. No cross-tenant access is possible.

## Role Status

Roles can be active or inactive:

```php
$role->status = 'inactive';
$role->save();
```

Inactive roles:
- Still exist in the database
- Can still be assigned to users (unless your app logic prevents it)
- Are returned in listings with their status

Your application should enforce status checking if needed.

## Super Admin Behavior

You can create a super admin pattern using wildcards:

```php
// config/roles.php
'seed' => [
    'map' => [
        'super-admin' => ['*'], // All permissions
    ],
],
```

Then sync:

```bash
php artisan roles:sync
```

The super-admin role will have all permissions.

### Checking for Super Admin

```php
// Option 1: Check for specific role
if ($user->hasRole('super-admin')) {
    // Full access
}

// Option 2: Check for wildcard permission (requires gate)
Gate::before(function ($user) {
    if ($user->hasRole('super-admin')) {
        return true;
    }
});
```

## Role Statistics

Get role statistics via the API:

```
GET /admin/acl/roles/stats
```

Response:

```json
{
    "data": {
        "total": 5,
        "active": 4,
        "inactive": 1,
        "deleted": 0,
        "with_permissions": 4
    }
}
```

## Assigning Permissions to Roles

### Via Sync (Replace All)

```php
$role->syncPermissions(['users.list', 'users.create']);
```

### Via Diff (Add/Remove)

```php
use Enadstack\LaravelRoles\Contracts\RolePermissionSyncServiceContract;

$syncService = app(RolePermissionSyncServiceContract::class);
$result = $syncService->diffSync($role, [
    'grant' => ['users.update'],
    'revoke' => ['users.delete'],
]);
```

See [Permission Matrix](permission-matrix.md) for the diff API.

## Events

The package dispatches events on role changes:

| Event | When |
|-------|------|
| `RoleCreated` | After role is created |
| `RoleUpdated` | After role is updated |
| `RoleDeleted` | After role is deleted |
| `PermissionsAssignedToRole` | After permissions are synced |

These events trigger cache invalidation.

## Next Steps

- [Permissions](permissions.md)
- [Permission Matrix](permission-matrix.md)
- [API Reference](api.md)

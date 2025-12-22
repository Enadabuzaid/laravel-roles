# Permissions

This document covers permission management in Laravel Roles.

## Permission Naming Conventions

Permissions follow a `{group}.{action}` convention:

```
users.list
users.create
users.show
users.update
users.delete
```

### Standard Actions

| Action | Description |
|--------|-------------|
| `list` | View list/index |
| `create` | Create new records |
| `show` | View single record |
| `update` | Edit records |
| `delete` | Delete records |

### Naming Best Practices

```
# Good
users.list
posts.publish
reports.export

# Bad
can_view_users       # Different convention
UserListPermission   # Too verbose
view                 # No group context
```

## Permission Groups

Permissions are organized into groups:

```php
// config/roles.php
'seed' => [
    'permission_groups' => [
        'users' => ['list', 'create', 'show', 'update', 'delete'],
        'roles' => ['list', 'create', 'show', 'update', 'delete'],
        'posts' => ['list', 'create', 'show', 'update', 'delete', 'publish'],
        'reports' => ['list', 'export'],
    ],
],
```

### Group Field

Permissions have a `group` field for filtering:

```php
Permission::create([
    'name' => 'users.list',
    'guard_name' => 'web',
    'group' => 'users', // For grouping
]);
```

### Querying by Group

```php
// Via model
Permission::where('group', 'users')->get();

// Via API
GET /admin/acl/permissions?group=users
GET /admin/acl/permissions/grouped
```

## Wildcards

The package supports wildcard patterns in permission syncing.

### Star Wildcard (*)

Matches ALL permissions:

```php
// Grant all permissions
'map' => [
    'super-admin' => ['*'],
],
```

### Group Wildcard ({group}.*)

Matches all permissions in a group:

```php
// Grant all user permissions
'map' => [
    'user-manager' => ['users.*'],
],

// Expands to:
// users.list, users.create, users.show, users.update, users.delete
```

### Mixed Wildcards

```php
'map' => [
    'viewer' => ['*.list', '*.show'],
    'editor' => ['users.*', 'posts.*'],
],
```

### Wildcard in API

Use wildcards in the diff endpoint:

```json
POST /admin/acl/roles/{id}/permissions/diff
{
    "grant": ["users.*"],
    "revoke": ["posts.delete"]
}
```

## Labels and Descriptions

### Single Language Mode (i18n disabled)

```php
Permission::create([
    'name' => 'users.list',
    'guard_name' => 'web',
    'description' => 'View the list of users',
]);
```

### Multi-Language Mode (i18n enabled)

```php
Permission::create([
    'name' => 'users.list',
    'guard_name' => 'web',
    'label' => ['en' => 'List Users', 'ar' => 'عرض المستخدمين'],
    'description' => ['en' => 'View the list of users', 'ar' => 'عرض قائمة المستخدمين'],
]);
```

### Seeding Labels

```php
// config/roles.php
'seed' => [
    'permission_labels' => [
        'users.list' => [
            'en' => 'List Users',
            'ar' => 'عرض المستخدمين',
        ],
    ],
],
```

## Localization Strategy

When i18n is enabled:

1. Labels and descriptions are stored as JSON
2. The API returns the appropriate locale based on request
3. Fallback to default locale if translation missing
4. Cache keys include locale to prevent mixing

### Setting Locale

```php
// In middleware or controller
app()->setLocale('ar');

// Or via Accept-Language header
Accept-Language: ar
```

### API Response with i18n

```json
{
    "data": {
        "id": 1,
        "name": "users.list",
        "label": "List Users",
        "description": "View the list of users"
    }
}
```

The response contains the resolved translation, not the JSON.

## Permission CRUD

### Creating Permissions

```php
$permissionService = app(PermissionServiceContract::class);
$permission = $permissionService->create([
    'name' => 'reports.export',
    'guard_name' => 'web',
    'group' => 'reports',
]);

// Via API
POST /admin/acl/permissions
{
    "name": "reports.export",
    "group": "reports"
}
```

### Updating Permissions

```php
$permissionService->update($permission, [
    'description' => 'Export reports to PDF',
]);

// Via API
PUT /admin/acl/permissions/{id}
{
    "description": "Export reports to PDF"
}
```

### Deleting Permissions

Permissions support soft deletes:

```php
$permissionService->delete($permission);

// Soft-deleted permissions can be restored
$permissionService->restore($permission);
```

## Permission Statistics

```
GET /admin/acl/permissions/stats
```

Response:

```json
{
    "data": {
        "total": 25,
        "by_group": {
            "users": 5,
            "roles": 5,
            "posts": 6
        }
    }
}
```

## Checking Permissions

The package uses Spatie's permission checking:

```php
// Direct check
$user->can('users.list');

// Via hasPermissionTo
$user->hasPermissionTo('users.list');

// Via role
$user->hasRole('admin');
```

## Next Steps

- [Permission Matrix](permission-matrix.md)
- [Roles](roles.md)
- [API Reference](api.md)

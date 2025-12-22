# Permission Matrix

This document explains the permission matrix feature and diff-based updates.

## Matrix Concept

The permission matrix is a grid showing which roles have which permissions:

```
                    | admin | editor | viewer |
--------------------|-------|--------|--------|
users.list          |   X   |   X    |   X    |
users.create        |   X   |   X    |        |
users.update        |   X   |   X    |        |
users.delete        |   X   |        |        |
posts.list          |   X   |   X    |   X    |
posts.publish       |   X   |   X    |        |
```

This view allows quick understanding and modification of role permissions.

## Getting the Matrix

### API Endpoint

```
GET /admin/acl/matrix
```

### Response Structure

```json
{
    "data": {
        "roles": [
            {"id": 1, "name": "admin", "guard_name": "web"},
            {"id": 2, "name": "editor", "guard_name": "web"}
        ],
        "permissions": [
            {"id": 1, "name": "users.list", "group": "users"},
            {"id": 2, "name": "users.create", "group": "users"}
        ],
        "matrix": [
            {
                "permission_id": 1,
                "permission_name": "users.list",
                "roles": {
                    "admin": {"has_permission": true},
                    "editor": {"has_permission": true}
                }
            }
        ]
    }
}
```

### Grouped Matrix

For UI with collapsible groups:

```
GET /admin/acl/matrix/grouped
```

Response groups permissions by their group field.

## Performance Design

The matrix is designed for efficiency:

### Query Optimization

- Maximum 3-5 queries regardless of data size
- Uses eager loading for relationships
- Bulk loads all role-permission mappings

### No N+1 Queries

Traditional approach (N+1):

```php
// Bad - 1 + N queries
foreach ($permissions as $permission) {
    foreach ($roles as $role) {
        $hasPermission = $role->hasPermissionTo($permission);
    }
}
```

Package approach (constant queries):

```php
// Good - 3 queries total
$matrix = $matrixService->build();
// 1 query: roles
// 1 query: permissions
// 1 query: role_has_permissions pivot
```

## Diff-Based Updates

Instead of syncing all permissions, use diff for partial updates.

### Diff Endpoint

```
POST /admin/acl/roles/{id}/permissions/diff
```

### Grant Permissions

```json
{
    "grant": ["users.list", "users.create"],
    "revoke": []
}
```

### Revoke Permissions

```json
{
    "grant": [],
    "revoke": ["users.delete"]
}
```

### Mixed Operations

```json
{
    "grant": ["posts.publish"],
    "revoke": ["posts.delete"]
}
```

### Response

```json
{
    "data": {
        "result": {
            "granted": ["posts.publish"],
            "revoked": ["posts.delete"],
            "skipped": {
                "already_granted": [],
                "not_assigned": [],
                "not_found": []
            }
        }
    }
}
```

## Group Toggling

Toggle all permissions in a group at once using wildcards.

### Grant Entire Group

```json
{
    "grant": ["users.*"],
    "revoke": []
}
```

Expands to: `users.list`, `users.create`, `users.show`, `users.update`, `users.delete`

### Revoke Entire Group

```json
{
    "grant": [],
    "revoke": ["users.*"]
}
```

### Grant All Permissions

```json
{
    "grant": ["*"],
    "revoke": []
}
```

## Idempotency

Diff operations are idempotent:

- Granting an already-granted permission is skipped
- Revoking a non-assigned permission is skipped
- Running the same diff twice has the same result

This makes bulk operations safe.

## Optimistic Updates

The Vue UI uses optimistic updates:

1. User toggles permission in UI
2. UI immediately shows new state
3. API request sent in background
4. On failure, UI rolls back to previous state

This provides instant feedback while maintaining data integrity.

## Caching Behavior

### Matrix Cache

The matrix response is cached:

```
Cache key: laravel_roles:{guard}:{tenant}:matrix
TTL: Configured via roles.cache.ttl
```

### Invalidation

Cache is invalidated on:

- Role created/updated/deleted
- Permission created/updated/deleted
- Permissions assigned/revoked via diff
- `roles:sync` command run

### Force Refresh

```
GET /admin/acl/matrix?refresh=true
```

## Using the Matrix Service

```php
use Enadstack\LaravelRoles\Contracts\PermissionMatrixServiceContract;

$matrixService = app(PermissionMatrixServiceContract::class);

// Get full matrix
$matrix = $matrixService->build();

// Get grouped matrix
$grouped = $matrixService->buildGrouped();

// Get for specific guard
$apiMatrix = $matrixService->forGuard('api');
```

## UI Integration

The Vue UI provides:

- Tabbed view by role
- Accordion view by permission group
- Single-click toggle for individual permissions
- Group-level toggle (all permissions in group)
- Real-time optimistic updates
- Rollback on API errors

See [Vue UI](ui-vue.md) for details.

## Next Steps

- [Vue UI](ui-vue.md)
- [API Reference](api.md)
- [Caching](caching.md)

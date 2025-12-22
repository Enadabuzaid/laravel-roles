# API Reference

This document covers all backend API endpoints.

## Base URL

All endpoints are prefixed with the configured prefix:

```
{prefix}/...
Default: /admin/acl/...
```

## Authentication

All endpoints require authentication via the configured middleware.

## Response Format

All responses follow this structure:

```json
{
    "data": { ... },
    "meta": { ... },
    "message": "Optional message"
}
```

Paginated responses include:

```json
{
    "data": [...],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 75
    }
}
```

## Roles Endpoints

### List Roles

```
GET /roles
```

Query Parameters:

| Parameter | Type | Description |
|-----------|------|-------------|
| `search` | string | Search by name |
| `guard` | string | Filter by guard |
| `status` | string | Filter by status (`active`, `inactive`) |
| `with_trashed` | boolean | Include soft-deleted |
| `only_trashed` | boolean | Only soft-deleted |
| `per_page` | integer | Items per page (default: 15) |
| `page` | integer | Page number |

### Get Role

```
GET /roles/{id}
```

### Create Role

```
POST /roles
```

Payload:

```json
{
    "name": "editor",
    "guard_name": "web",
    "description": "Can edit content",
    "permission_ids": [1, 2, 3]
}
```

### Update Role

```
PUT /roles/{id}
```

Payload:

```json
{
    "name": "updated-name",
    "description": "Updated description"
}
```

### Delete Role (Soft)

```
DELETE /roles/{id}
```

### Restore Role

```
POST /roles/{id}/restore
```

### Force Delete Role

```
DELETE /roles/{id}/force
```

### Role Statistics

```
GET /roles/stats
```

Response:

```json
{
    "data": {
        "total": 10,
        "active": 8,
        "inactive": 2,
        "deleted": 0
    }
}
```

## Permissions Endpoints

### List Permissions

```
GET /permissions
```

Query Parameters:

| Parameter | Type | Description |
|-----------|------|-------------|
| `search` | string | Search by name |
| `guard` | string | Filter by guard |
| `group` | string | Filter by group |
| `per_page` | integer | Items per page |
| `page` | integer | Page number |

### Get Grouped Permissions

```
GET /permissions/grouped
```

Returns permissions organized by group.

### Get Permission

```
GET /permissions/{id}
```

### Create Permission

```
POST /permissions
```

Payload:

```json
{
    "name": "reports.export",
    "guard_name": "web",
    "group": "reports",
    "description": "Export reports to PDF"
}
```

### Update Permission

```
PUT /permissions/{id}
```

### Delete Permission

```
DELETE /permissions/{id}
```

### Permission Statistics

```
GET /permissions/stats
```

## Matrix Endpoints

### Get Permission Matrix

```
GET /matrix
```

Query Parameters:

| Parameter | Type | Description |
|-----------|------|-------------|
| `guard` | string | Filter by guard |
| `refresh` | boolean | Force cache refresh |

### Get Grouped Matrix

```
GET /matrix/grouped
```

### Diff Sync Permissions

```
POST /roles/{id}/permissions/diff
```

Payload:

```json
{
    "grant": ["users.list", "users.create", "posts.*"],
    "revoke": ["users.delete"]
}
```

Response:

```json
{
    "data": {
        "result": {
            "granted": ["users.list", "users.create", "posts.list", "posts.create"],
            "revoked": ["users.delete"],
            "skipped": {
                "already_granted": [],
                "not_assigned": [],
                "not_found": []
            }
        }
    }
}
```

### Toggle Single Permission

```
POST /roles/{id}/permissions/toggle
```

Payload:

```json
{
    "permission_id": 5,
    "permission_name": "users.list",
    "has_permission": true
}
```

## Current User Endpoints

These require `routes.expose_me = true`.

### Get Current User Roles

```
GET /me/roles
```

### Get Current User Permissions

```
GET /me/permissions
```

### Get Current User ACL

```
GET /me/acl
```

Response:

```json
{
    "data": {
        "roles": [...],
        "permissions": [...],
        "tenant": {
            "mode": "single",
            "id": null
        },
        "guard": "web"
    }
}
```

## Error Handling

### Validation Errors

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "name": ["The name field is required."]
    }
}
```

Status: `422 Unprocessable Entity`

### Not Found

```json
{
    "message": "Role not found."
}
```

Status: `404 Not Found`

### Unauthorized

```json
{
    "message": "Unauthenticated."
}
```

Status: `401 Unauthorized`

### Forbidden

```json
{
    "message": "This action is unauthorized."
}
```

Status: `403 Forbidden`

## Rate Limiting

The package does not impose rate limiting. Apply via Laravel's route middleware if needed.

## Next Steps

- [Vue UI](ui-vue.md)
- [Permission Matrix](permission-matrix.md)
- [Caching](caching.md)

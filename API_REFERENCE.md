# API Reference - Laravel Roles & Permissions

Complete API reference for all available endpoints in the Laravel Roles & Permissions package.

## Base URL

All endpoints are prefixed with the configured route prefix (default: `/admin/acl`).

## Authentication

All endpoints require authentication. Configure middleware in `config/roles.php`:

```php
'routes' => [
    'middleware' => ['api', 'auth:sanctum'],
]
```

## Response Format

All responses follow standard Laravel conventions:

### Success Response
```json
{
    "data": { },
    "message": "Operation successful"
}
```

### Error Response
```json
{
    "message": "Error message",
    "errors": {
        "field": ["Validation error message"]
    }
}
```

### Pagination Response
```json
{
    "data": [],
    "links": {
        "first": "...",
        "last": "...",
        "prev": null,
        "next": "..."
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 5,
        "per_page": 20,
        "to": 20,
        "total": 100
    }
}
```

---

## Roles Endpoints

### List Roles

Get a paginated list of roles.

**Endpoint:** `GET /admin/acl/roles`

**Query Parameters:**
- `search` (string, optional) - Search in role name and description
- `guard` (string, optional) - Filter by guard name
- `sort` (string, optional) - Sort field (default: `id`)
- `direction` (string, optional) - Sort direction: `asc` or `desc` (default: `desc`)
- `per_page` (integer, optional) - Items per page (default: 20, max: 100)

**Example Request:**
```bash
curl -X GET "http://yourapp.test/admin/acl/roles?search=admin&sort=created_at&per_page=10" \
  -H "Authorization: Bearer {token}"
```

**Example Response:**
```json
{
    "data": [
        {
            "id": 1,
            "name": "super-admin",
            "guard_name": "web",
            "label": {"en": "Super Admin"},
            "description": {"en": "Full system access"},
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z",
            "deleted_at": null
        }
    ],
    "links": {...},
    "meta": {...}
}
```

---

### Show Role

Get details of a specific role.

**Endpoint:** `GET /admin/acl/roles/{id}`

**Path Parameters:**
- `id` (integer, required) - Role ID

**Example Request:**
```bash
curl -X GET "http://yourapp.test/admin/acl/roles/1" \
  -H "Authorization: Bearer {token}"
```

**Example Response:**
```json
{
    "id": 1,
    "name": "super-admin",
    "guard_name": "web",
    "label": {"en": "Super Admin"},
    "description": {"en": "Full system access"},
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z",
    "deleted_at": null
}
```

---

### Create Role

Create a new role.

**Endpoint:** `POST /admin/acl/roles`

**Request Body:**
- `name` (string, required) - Unique role name (max: 255)
- `guard_name` (string, optional) - Guard name (default: from config)
- `label` (object, optional) - Multi-language label (i18n enabled)
- `description` (object|string, optional) - Role description

**Example Request:**
```bash
curl -X POST "http://yourapp.test/admin/acl/roles" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "editor",
    "label": {"en": "Editor", "ar": "محرر"},
    "description": {"en": "Content editor role"}
  }'
```

**Example Response:**
```json
{
    "id": 5,
    "name": "editor",
    "guard_name": "web",
    "label": {"en": "Editor", "ar": "محرر"},
    "description": {"en": "Content editor role"},
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

---

### Update Role

Update an existing role.

**Endpoint:** `PUT /admin/acl/roles/{id}`

**Path Parameters:**
- `id` (integer, required) - Role ID

**Request Body:**
- `name` (string, optional) - Unique role name (max: 255)
- `label` (object, optional) - Multi-language label
- `description` (object|string, optional) - Role description

**Example Request:**
```bash
curl -X PUT "http://yourapp.test/admin/acl/roles/5" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "senior-editor",
    "label": {"en": "Senior Editor"}
  }'
```

---

### Delete Role (Soft Delete)

Soft delete a role (can be restored).

**Endpoint:** `DELETE /admin/acl/roles/{id}`

**Path Parameters:**
- `id` (integer, required) - Role ID

**Example Request:**
```bash
curl -X DELETE "http://yourapp.test/admin/acl/roles/5" \
  -H "Authorization: Bearer {token}"
```

**Response:** 204 No Content

---

### Force Delete Role

Permanently delete a role (cannot be restored).

**Endpoint:** `DELETE /admin/acl/roles/{id}/force`

**Path Parameters:**
- `id` (integer, required) - Role ID

**Example Request:**
```bash
curl -X DELETE "http://yourapp.test/admin/acl/roles/5/force" \
  -H "Authorization: Bearer {token}"
```

**Example Response:**
```json
{
    "message": "Role permanently deleted"
}
```

---

### Restore Role

Restore a soft-deleted role.

**Endpoint:** `POST /admin/acl/roles/{id}/restore`

**Path Parameters:**
- `id` (integer, required) - Role ID

**Example Request:**
```bash
curl -X POST "http://yourapp.test/admin/acl/roles/5/restore" \
  -H "Authorization: Bearer {token}"
```

**Example Response:**
```json
{
    "message": "Role restored successfully"
}
```

---

### Bulk Delete Roles

Soft delete multiple roles at once.

**Endpoint:** `POST /admin/acl/roles/bulk-delete`

**Request Body:**
- `ids` (array, required) - Array of role IDs
- `ids.*` (integer, required) - Each ID must exist

**Example Request:**
```bash
curl -X POST "http://yourapp.test/admin/acl/roles/bulk-delete" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "ids": [1, 2, 3]
  }'
```

**Example Response:**
```json
{
    "message": "Bulk delete completed",
    "results": {
        "success": [1, 3],
        "failed": [
            {
                "id": 2,
                "reason": "Role is protected"
            }
        ]
    }
}
```

---

### Bulk Restore Roles

Restore multiple soft-deleted roles at once.

**Endpoint:** `POST /admin/acl/roles/bulk-restore`

**Request Body:**
- `ids` (array, required) - Array of role IDs
- `ids.*` (integer, required)

**Example Request:**
```bash
curl -X POST "http://yourapp.test/admin/acl/roles/bulk-restore" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "ids": [1, 2, 3]
  }'
```

**Example Response:**
```json
{
    "message": "Bulk restore completed",
    "results": {
        "success": [1, 3],
        "failed": [
            {
                "id": 2,
                "reason": "Not found or not deleted"
            }
        ]
    }
}
```

---

### Get Recent Roles

Get recently created roles.

**Endpoint:** `GET /admin/acl/roles-recent`

**Query Parameters:**
- `limit` (integer, optional) - Number of items to return (default: 10)

**Example Request:**
```bash
curl -X GET "http://yourapp.test/admin/acl/roles-recent?limit=5" \
  -H "Authorization: Bearer {token}"
```

**Example Response:**
```json
[
    {
        "id": 10,
        "name": "reviewer",
        "guard_name": "web",
        "label": {"en": "Reviewer"},
        "created_at": "2024-01-10T00:00:00.000000Z"
    }
]
```

---

### Get Role Statistics

Get statistical data about roles.

**Endpoint:** `GET /admin/acl/roles-stats`

**Example Request:**
```bash
curl -X GET "http://yourapp.test/admin/acl/roles-stats" \
  -H "Authorization: Bearer {token}"
```

**Example Response:**
```json
{
    "total": 10,
    "active": 8,
    "deleted": 2,
    "with_permissions": 6,
    "without_permissions": 4
}
```

---

### Assign Permissions to Role

Assign or replace permissions for a role.

**Endpoint:** `POST /admin/acl/roles/{id}/permissions`

**Path Parameters:**
- `id` (integer, required) - Role ID

**Request Body:**
- `permission_ids` (array, required) - Array of permission IDs
- `permission_ids.*` (integer, required) - Each ID must exist

**Example Request:**
```bash
curl -X POST "http://yourapp.test/admin/acl/roles/5/permissions" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "permission_ids": [1, 2, 3, 4, 5]
  }'
```

**Example Response:**
```json
{
    "message": "Permissions assigned successfully",
    "role": {
        "id": 5,
        "name": "editor",
        "permissions": [...]
    }
}
```

---

### Get Role Permissions

Get all permissions assigned to a role.

**Endpoint:** `GET /admin/acl/roles/{id}/permissions`

**Path Parameters:**
- `id` (integer, required) - Role ID

**Example Request:**
```bash
curl -X GET "http://yourapp.test/admin/acl/roles/5/permissions" \
  -H "Authorization: Bearer {token}"
```

**Example Response:**
```json
[
    {
        "id": 1,
        "name": "users.list",
        "group": "users",
        "label": {"en": "List Users"}
    }
]
```

---

### Get Permissions Grouped by Role

Get all permissions grouped by their assigned roles.

**Endpoint:** `GET /admin/acl/roles-permissions`

**Example Request:**
```bash
curl -X GET "http://yourapp.test/admin/acl/roles-permissions" \
  -H "Authorization: Bearer {token}"
```

**Example Response:**
```json
[
    {
        "id": 1,
        "name": "super-admin",
        "label": {"en": "Super Admin"},
        "permissions": [
            {
                "id": 1,
                "name": "users.list",
                "label": {"en": "List Users"},
                "group": "users"
            }
        ]
    }
]
```

---

## Permissions Endpoints

### List Permissions

Get a paginated list of permissions.

**Endpoint:** `GET /admin/acl/permissions`

**Query Parameters:**
- `q` or `search` (string, optional) - Search in name, description, label, group
- `group` (string, optional) - Filter by group
- `guard` (string, optional) - Filter by guard name
- `sort` (string, optional) - Sort field: `id`, `name`, `group`, `created_at` (default: `id`)
- `dir` or `direction` (string, optional) - Sort direction: `asc` or `desc` (default: `desc`)
- `per_page` (integer, optional) - Items per page (default: 20, max: 100)

**Example Request:**
```bash
curl -X GET "http://yourapp.test/admin/acl/permissions?group=users&sort=name&per_page=50" \
  -H "Authorization: Bearer {token}"
```

---

### Show Permission

Get details of a specific permission.

**Endpoint:** `GET /admin/acl/permissions/{id}`

**Path Parameters:**
- `id` (integer, required) - Permission ID

---

### Create Permission

Create a new permission.

**Endpoint:** `POST /admin/acl/permissions`

**Request Body:**
- `name` (string, required) - Unique permission name (max: 255)
- `guard_name` (string, optional) - Guard name
- `group` (string, optional) - Permission group (max: 255)
- `label` (object, optional) - Multi-language label
- `description` (object|string, optional) - Permission description
- `group_label` (object, optional) - Multi-language group label

**Example Request:**
```bash
curl -X POST "http://yourapp.test/admin/acl/permissions" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "posts.publish",
    "group": "posts",
    "label": {"en": "Publish Posts", "ar": "نشر المنشورات"},
    "group_label": {"en": "Posts", "ar": "المنشورات"}
  }'
```

---

### Update Permission

Update an existing permission.

**Endpoint:** `PUT /admin/acl/permissions/{id}`

**Path Parameters:**
- `id` (integer, required) - Permission ID

**Request Body:**
- `name` (string, optional) - Permission name
- `group` (string, optional) - Permission group
- `label` (object, optional) - Multi-language label
- `description` (object|string, optional)
- `group_label` (object, optional)

---

### Delete Permission (Soft Delete)

Soft delete a permission.

**Endpoint:** `DELETE /admin/acl/permissions/{id}`

**Path Parameters:**
- `id` (integer, required) - Permission ID

**Response:** 204 No Content

---

### Force Delete Permission

Permanently delete a permission.

**Endpoint:** `DELETE /admin/acl/permissions/{id}/force`

**Path Parameters:**
- `id` (integer, required) - Permission ID

---

### Restore Permission

Restore a soft-deleted permission.

**Endpoint:** `POST /admin/acl/permissions/{id}/restore`

**Path Parameters:**
- `id` (integer, required) - Permission ID

---

### Get Recent Permissions

Get recently created permissions.

**Endpoint:** `GET /admin/acl/permissions-recent`

**Query Parameters:**
- `limit` (integer, optional) - Number of items (default: 10)

---

### Get Permission Statistics

Get statistical data about permissions.

**Endpoint:** `GET /admin/acl/permissions-stats`

**Example Response:**
```json
{
    "total": 50,
    "active": 45,
    "deleted": 5,
    "assigned": 40,
    "unassigned": 10,
    "by_group": {
        "users": 7,
        "roles": 7,
        "posts": 10
    }
}
```

---

### Get Permission Matrix

Get a matrix of all roles and permissions showing which roles have which permissions.

**Endpoint:** `GET /admin/acl/permissions-matrix`

**Example Request:**
```bash
curl -X GET "http://yourapp.test/admin/acl/permissions-matrix" \
  -H "Authorization: Bearer {token}"
```

**Example Response:**
```json
{
    "roles": [
        {"id": 1, "name": "super-admin", "label": {"en": "Super Admin"}},
        {"id": 2, "name": "admin", "label": {"en": "Admin"}},
        {"id": 3, "name": "editor", "label": {"en": "Editor"}}
    ],
    "matrix": [
        {
            "permission_id": 1,
            "permission_name": "users.list",
            "permission_label": {"en": "List Users"},
            "permission_group": "users",
            "roles": {
                "super-admin": {
                    "role_id": 1,
                    "has_permission": true
                },
                "admin": {
                    "role_id": 2,
                    "has_permission": true
                },
                "editor": {
                    "role_id": 3,
                    "has_permission": false
                }
            }
        }
    ]
}
```

---

### Get Permission Groups

Get permissions organized by their groups.

**Endpoint:** `GET /admin/acl/permission-groups`

**Example Response:**
```json
{
    "users": {
        "label": {"en": "Users", "ar": "المستخدمون"},
        "permissions": [
            {"id": 1, "name": "users.list", "label": {"en": "List Users"}},
            {"id": 2, "name": "users.create", "label": {"en": "Create User"}}
        ]
    },
    "roles": {
        "label": {"en": "Roles", "ar": "الأدوار"},
        "permissions": [
            {"id": 8, "name": "roles.list", "label": {"en": "List Roles"}}
        ]
    }
}
```

---

## Error Codes

Common HTTP status codes:

- `200 OK` - Request successful
- `201 Created` - Resource created successfully
- `204 No Content` - Request successful with no response body
- `400 Bad Request` - Invalid request data
- `401 Unauthorized` - Authentication required
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation failed
- `500 Internal Server Error` - Server error

---

## Rate Limiting

API endpoints may be rate-limited based on your application's configuration. Check the `X-RateLimit-*` headers in responses:

- `X-RateLimit-Limit` - Maximum requests per window
- `X-RateLimit-Remaining` - Remaining requests in current window
- `X-RateLimit-Reset` - Timestamp when the rate limit resets

---

## Postman Collection

A Postman collection for all endpoints is available in the `/docs` directory (coming soon).

---

## Need Help?

- [GitHub Issues](https://github.com/Enadabuzaid/laravel-roles/issues)
- [Full Documentation](README.md)

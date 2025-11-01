# Endpoints Summary

Quick reference of all available endpoints in the Laravel Roles & Permissions package.

**Base URL**: `/admin/acl` (configurable via `config/roles.php`)

## Roles (14 endpoints)

### CRUD Operations
| Method | Endpoint | Action | Description |
|--------|----------|--------|-------------|
| GET | `/roles` | index | List all roles (paginated) |
| POST | `/roles` | store | Create a new role |
| GET | `/roles/{id}` | show | Get role details |
| PUT | `/roles/{id}` | update | Update a role |
| DELETE | `/roles/{id}` | destroy | Soft delete a role |

### Advanced Operations
| Method | Endpoint | Action | Description |
|--------|----------|--------|-------------|
| POST | `/roles/{id}/restore` | restore | Restore a soft-deleted role |
| DELETE | `/roles/{id}/force` | forceDelete | Permanently delete a role |
| POST | `/roles/bulk-delete` | bulkDelete | Soft delete multiple roles |
| POST | `/roles/bulk-restore` | bulkRestore | Restore multiple roles |

### Data & Analytics
| Method | Endpoint | Action | Description |
|--------|----------|--------|-------------|
| GET | `/roles-recent` | recent | Get recently created roles |
| GET | `/roles-stats` | stats | Get role statistics |

### Permission Management
| Method | Endpoint | Action | Description |
|--------|----------|--------|-------------|
| POST | `/roles/{id}/permissions` | assignPermissions | Assign permissions to role |
| GET | `/roles/{id}/permissions` | permissions | Get role's permissions |
| GET | `/roles-permissions` | permissionsGroupedByRole | All permissions grouped by role |

## Permissions (11 endpoints)

### CRUD Operations
| Method | Endpoint | Action | Description |
|--------|----------|--------|-------------|
| GET | `/permissions` | index | List all permissions (paginated) |
| POST | `/permissions` | store | Create a new permission |
| GET | `/permissions/{id}` | show | Get permission details |
| PUT | `/permissions/{id}` | update | Update a permission |
| DELETE | `/permissions/{id}` | destroy | Soft delete a permission |

### Advanced Operations
| Method | Endpoint | Action | Description |
|--------|----------|--------|-------------|
| POST | `/permissions/{id}/restore` | restore | Restore a soft-deleted permission |
| DELETE | `/permissions/{id}/force` | forceDelete | Permanently delete a permission |

### Data & Analytics
| Method | Endpoint | Action | Description |
|--------|----------|--------|-------------|
| GET | `/permissions-recent` | recent | Get recently created permissions |
| GET | `/permissions-stats` | stats | Get permission statistics |
| GET | `/permissions-matrix` | matrix | Roles × Permissions matrix |
| GET | `/permission-groups` | groups | Permissions grouped by category |

## Total: 25 Endpoints

- **Roles**: 14 endpoints
- **Permissions**: 11 endpoints

## Common Query Parameters

### List Endpoints
- `search` or `q` - Search term
- `sort` - Sort field (id, name, created_at, etc.)
- `direction` or `dir` - Sort direction (asc, desc)
- `per_page` - Results per page (max: 100)
- `page` - Page number
- `guard` - Guard name filter
- `group` - Permission group filter (permissions only)

### Recent Endpoints
- `limit` - Number of items (default: 10)

## Authentication

All endpoints require authentication. Configure middleware in `config/roles.php`:

```php
'routes' => [
    'middleware' => ['api', 'auth:sanctum'],
]
```

## Route Naming Convention

All routes are named with the `roles.` prefix:

- `roles.index`
- `roles.store`
- `roles.show`
- `roles.update`
- `roles.destroy`
- `roles.restore`
- `roles.force-delete`
- `roles.bulk-delete`
- `roles.bulk-restore`
- `roles.recent`
- `roles.stats`
- `roles.assign-permissions`
- `roles.permissions`
- `roles.permissions-grouped`
- `roles.permissions.index`
- `roles.permissions.store`
- `roles.permissions.show`
- `roles.permissions.update`
- `roles.permissions.destroy`
- `roles.permissions.restore`
- `roles.permissions.force-delete`
- `roles.permissions.recent`
- `roles.permissions.stats`
- `roles.permissions.matrix`
- `roles.permissions.groups`

## Usage in Laravel

```php
// Generate URL
$url = route('roles.index');

// Redirect to route
return redirect()->route('roles.show', ['role' => $roleId]);

// Route in view
<a href="{{ route('roles.permissions', ['id' => $role->id]) }}">View Permissions</a>
```

## Example API Calls

### cURL
```bash
# List roles
curl -X GET "http://yourapp.test/admin/acl/roles" \
  -H "Authorization: Bearer {token}"

# Create role
curl -X POST "http://yourapp.test/admin/acl/roles" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"name":"editor","label":{"en":"Editor"}}'

# Get permission matrix
curl -X GET "http://yourapp.test/admin/acl/permissions-matrix" \
  -H "Authorization: Bearer {token}"
```

### JavaScript (Axios)
```javascript
// List permissions
const response = await axios.get('/admin/acl/permissions', {
  params: { group: 'users', per_page: 50 }
});

// Assign permissions to role
await axios.post(`/admin/acl/roles/${roleId}/permissions`, {
  permission_ids: [1, 2, 3, 4]
});

// Get role statistics
const stats = await axios.get('/admin/acl/roles-stats');
```

### PHP (Laravel HTTP Client)
```php
use Illuminate\Support\Facades\Http;

// List roles
$response = Http::get('/admin/acl/roles', [
    'search' => 'admin',
    'per_page' => 20
]);

// Bulk restore roles
$response = Http::post('/admin/acl/roles/bulk-restore', [
    'ids' => [1, 2, 3]
]);
```

## Rate Limiting

To add rate limiting to the routes:

```php
// config/roles.php
'routes' => [
    'middleware' => ['api', 'auth:sanctum', 'throttle:60,1'],
]
```

## Custom Route Configuration

You can customize the route configuration:

```php
// config/roles.php
return [
    'routes' => [
        'prefix' => 'api/v1/acl',           // Change prefix
        'middleware' => ['api', 'auth'],     // Change middleware
        'guard' => 'api',                    // Change guard
    ],
];
```

## See Also

- [Complete API Reference](API_REFERENCE.md) - Detailed documentation for each endpoint
- [README](README.md) - Installation and usage guide
- [CHANGELOG](CHANGELOG.md) - Version history and changes

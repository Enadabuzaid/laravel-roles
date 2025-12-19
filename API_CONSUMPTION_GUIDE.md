# API Consumption Guide - Using Laravel Roles Package APIs in Your Main Project

## Problem & Solution

### The Issue
When calling `http://gawak.test/admin/acl/roles-stats` from your RoleService in the main project, you're getting `null` instead of the expected data.

### The Solution
The API returns data in a standardized format with a `data` wrapper. Here's how to properly consume it.

---

## API Response Format

All endpoints return responses in this format:

```json
{
  "success": true,
  "data": {
    // Your actual data here
  }
}
```

For the `/roles-stats` endpoint specifically:

```json
{
  "success": true,
  "data": {
    "total": 15,
    "active": 12,
    "deleted": 3,
    "with_permissions": 10,
    "without_permissions": 2,
    "growth": {
      "last_7_days": {
        "current": 5,
        "previous": 3,
        "difference": 2,
        "percentage": 66.67,
        "trend": "up"
      },
      "last_30_days": { ... },
      "last_3_months": { ... },
      "last_6_months": { ... },
      "last_year": { ... }
    }
  }
}
```

---

## RoleService Implementation in Main Project

### Option 1: Complete Service Class (Recommended)

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RoleService
{
    protected string $baseUrl;
    protected int $timeout;
    protected int $cacheTime;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('app.url'), '/') . '/admin/acl';
        $this->timeout = config('services.roles_api.timeout', 30);
        $this->cacheTime = config('services.roles_api.cache_time', 300); // 5 minutes
    }

    /**
     * Get role statistics with growth data
     */
    public function getRoleStats(bool $fresh = false): array
    {
        $cacheKey = 'role_stats';

        if (!$fresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get("{$this->baseUrl}/roles-stats");

            if ($response->successful()) {
                $data = $response->json();
                
                // API returns: { "success": true, "data": {...} }
                if (isset($data['success']) && $data['success'] === true) {
                    $stats = $data['data'] ?? $this->getDefaultStats();
                    Cache::put($cacheKey, $stats, $this->cacheTime);
                    return $stats;
                }

                Log::warning('Role stats API returned unsuccessful response', [
                    'response' => $data,
                ]);

                return $this->getDefaultStats();
            }

            Log::warning('Role stats API returned non-successful HTTP status', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->getDefaultStats();

        } catch (\Exception $e) {
            Log::error('Failed to fetch role stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->getDefaultStats();
        }
    }

    /**
     * Get all roles with pagination
     */
    public function getRoles(array $filters = [], int $perPage = 20): array
    {
        try {
            $query = http_build_query(array_merge([
                'per_page' => $perPage,
            ], $filters));

            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get("{$this->baseUrl}/roles?{$query}");

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            Log::warning('Get roles API failed', [
                'status' => $response->status(),
            ]);

            return [];

        } catch (\Exception $e) {
            Log::error('Failed to fetch roles', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get a specific role by ID
     */
    public function getRole(int $roleId): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get("{$this->baseUrl}/roles/{$roleId}");

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? null;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to fetch role', [
                'role_id' => $roleId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get recent roles
     */
    public function getRecentRoles(int $limit = 10): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get("{$this->baseUrl}/roles-recent?limit={$limit}");

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Failed to fetch recent roles', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get permission statistics
     */
    public function getPermissionStats(bool $fresh = false): array
    {
        $cacheKey = 'permission_stats';

        if (!$fresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get("{$this->baseUrl}/permissions-stats");

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['success']) && $data['success'] === true) {
                    $stats = $data['data'] ?? $this->getDefaultPermissionStats();
                    Cache::put($cacheKey, $stats, $this->cacheTime);
                    return $stats;
                }

                return $this->getDefaultPermissionStats();
            }

            return $this->getDefaultPermissionStats();

        } catch (\Exception $e) {
            Log::error('Failed to fetch permission stats', [
                'error' => $e->getMessage(),
            ]);

            return $this->getDefaultPermissionStats();
        }
    }

    /**
     * Get permissions grouped by group
     */
    public function getPermissionGroups(): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get("{$this->baseUrl}/permission-groups");

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Failed to fetch permission groups', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Default stats structure when API fails
     */
    protected function getDefaultStats(): array
    {
        return [
            'total' => 0,
            'active' => 0,
            'deleted' => 0,
            'with_permissions' => 0,
            'without_permissions' => 0,
            'growth' => [
                'last_7_days' => $this->getDefaultGrowth(),
                'last_30_days' => $this->getDefaultGrowth(),
                'last_3_months' => $this->getDefaultGrowth(),
                'last_6_months' => $this->getDefaultGrowth(),
                'last_year' => $this->getDefaultGrowth(),
            ],
        ];
    }

    /**
     * Default permission stats structure
     */
    protected function getDefaultPermissionStats(): array
    {
        return [
            'total' => 0,
            'active' => 0,
            'deleted' => 0,
            'assigned' => 0,
            'unassigned' => 0,
            'by_group' => [],
            'growth' => [
                'last_7_days' => $this->getDefaultGrowth(),
                'last_30_days' => $this->getDefaultGrowth(),
                'last_3_months' => $this->getDefaultGrowth(),
                'last_6_months' => $this->getDefaultGrowth(),
                'last_year' => $this->getDefaultGrowth(),
            ],
        ];
    }

    /**
     * Default growth structure
     */
    protected function getDefaultGrowth(): array
    {
        return [
            'current' => 0,
            'previous' => 0,
            'difference' => 0,
            'percentage' => 0,
            'trend' => 'stable',
        ];
    }
}
```

---

## Configuration

Add to your `config/services.php`:

```php
return [
    // ...existing services...

    'roles_api' => [
        'timeout' => env('ROLES_API_TIMEOUT', 30),
        'cache_time' => env('ROLES_API_CACHE_TIME', 300), // 5 minutes
    ],
];
```

Add to your `.env`:

```env
ROLES_API_TIMEOUT=30
ROLES_API_CACHE_TIME=300
```

---

## Usage Examples

### In Your Controller

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RoleService;

class DashboardController extends Controller
{
    protected RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function index()
    {
        // Get role stats with growth
        $roleStats = $this->roleService->getRoleStats();

        // Get permission stats
        $permissionStats = $this->roleService->getPermissionStats();

        // Get recent roles
        $recentRoles = $this->roleService->getRecentRoles(5);

        return view('admin.dashboard', compact(
            'roleStats',
            'permissionStats',
            'recentRoles'
        ));
    }

    public function roles()
    {
        $filters = [
            'search' => request('search'),
            'guard' => request('guard'),
            'sort' => request('sort', 'id'),
            'direction' => request('direction', 'desc'),
        ];

        $roles = $this->roleService->getRoles($filters, 20);

        return view('admin.roles.index', compact('roles'));
    }
}
```

### In Your Blade Views

```blade
{{-- Dashboard Stats --}}
<div class="stats-grid">
    <div class="stat-card">
        <h3>Total Roles</h3>
        <p class="stat-number">{{ $roleStats['total'] }}</p>
    </div>

    <div class="stat-card">
        <h3>Active Roles</h3>
        <p class="stat-number">{{ $roleStats['active'] }}</p>
    </div>

    <div class="stat-card">
        <h3>7-Day Growth</h3>
        <p class="stat-number {{ $roleStats['growth']['last_7_days']['trend'] === 'up' ? 'text-success' : 'text-danger' }}">
            {{ $roleStats['growth']['last_7_days']['percentage'] }}%
            @if($roleStats['growth']['last_7_days']['trend'] === 'up')
                <i class="icon-arrow-up"></i>
            @else
                <i class="icon-arrow-down"></i>
            @endif
        </p>
    </div>
</div>

{{-- Growth Chart Data --}}
<script>
const growthData = {
    labels: ['7 Days', '30 Days', '3 Months', '6 Months', '1 Year'],
    datasets: [{
        label: 'Role Growth',
        data: [
            {{ $roleStats['growth']['last_7_days']['percentage'] }},
            {{ $roleStats['growth']['last_30_days']['percentage'] }},
            {{ $roleStats['growth']['last_3_months']['percentage'] }},
            {{ $roleStats['growth']['last_6_months']['percentage'] }},
            {{ $roleStats['growth']['last_year']['percentage'] }}
        ]
    }]
};
</script>
```

---

## Testing the API

### Option 1: Using Tinker

```bash
php artisan tinker

# Test the service
$roleService = app(\App\Services\RoleService::class);
$stats = $roleService->getRoleStats();
dd($stats);
```

### Option 2: Using cURL

```bash
# Direct API call
curl -X GET "http://gawak.test/admin/acl/roles-stats" \
  -H "Accept: application/json"

# Should return:
# {
#   "success": true,
#   "data": {
#     "total": 15,
#     "active": 12,
#     ...
#   }
# }
```

### Option 3: Using Postman/Insomnia

1. **URL:** `GET http://gawak.test/admin/acl/roles-stats`
2. **Headers:**
   - `Accept: application/json`
   - `Authorization: Bearer YOUR_TOKEN` (if using auth middleware)
3. **Expected Response:** JSON with `success` and `data` keys

---

## Troubleshooting

### Issue 1: Getting `null` in Response

**Problem:** `$response->json('data', [])` returns null

**Solution:** The response structure has changed. Use this instead:

```php
$response = Http::get("{$this->baseUrl}/roles-stats");

if ($response->successful()) {
    $data = $response->json(); // Get full response
    
    // Check success flag
    if (isset($data['success']) && $data['success'] === true) {
        return $data['data'] ?? []; // Extract data from wrapper
    }
}
```

### Issue 2: Authentication Required

**Problem:** API returns 401 Unauthorized

**Solution:** Add authentication to your HTTP request:

```php
$response = Http::withToken(session('api_token'))
    ->get("{$this->baseUrl}/roles-stats");
```

Or configure the package middleware in `config/roles.php`:

```php
'routes' => [
    'middleware' => ['api'], // Remove 'auth' if not needed
],
```

### Issue 3: CORS Errors

**Problem:** CORS errors when calling from frontend

**Solution:** Add CORS middleware or configure CORS in `config/cors.php`

### Issue 4: Timeout Errors

**Problem:** Request times out

**Solution:** Increase timeout:

```php
$response = Http::timeout(60)->get("{$this->baseUrl}/roles-stats");
```

---

## API Endpoints Reference

### Role Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/acl/roles` | List all roles (paginated) |
| POST | `/admin/acl/roles` | Create new role |
| GET | `/admin/acl/roles/{id}` | Get specific role |
| PUT | `/admin/acl/roles/{id}` | Update role |
| DELETE | `/admin/acl/roles/{id}` | Delete role (soft) |
| GET | `/admin/acl/roles-stats` | Get role statistics |
| GET | `/admin/acl/roles-recent` | Get recent roles |

### Permission Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/acl/permissions` | List all permissions (paginated) |
| GET | `/admin/acl/permissions-stats` | Get permission statistics |
| GET | `/admin/acl/permission-groups` | Get grouped permissions |
| GET | `/admin/acl/permissions-matrix` | Get permission matrix |

---

## Response Examples

### Success Response
```json
{
  "success": true,
  "data": {
    // Your data here
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message here",
  "errors": {
    "field": ["Validation error"]
  }
}
```

### Paginated Response
```json
{
  "success": true,
  "data": [
    // Items here
  ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 20,
    "to": 20,
    "total": 100
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

---

## Summary

The key fix for your issue:

**Before (Getting null):**
```php
return $response->json('data', []); // Wrong - doesn't work with new format
```

**After (Getting data):**
```php
$data = $response->json();
if (isset($data['success']) && $data['success'] === true) {
    return $data['data'] ?? [];
}
```

The API now returns a standardized format with a `success` flag and `data` wrapper for all responses!


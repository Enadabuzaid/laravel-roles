# API Response Trait and Growth Statistics Guide

This guide explains how to use the new standardized API response format and growth statistics feature.

## API Response Trait

The `ApiResponseTrait` provides standardized API response formats across all controllers.

### Available Response Methods

#### 1. Success Response
```php
$this->successResponse($data, 'Operation successful', 200);
```

#### 2. Error Response
```php
$this->errorResponse('Error message', 400, ['field' => 'validation error']);
```

#### 3. Paginated Response
```php
$this->paginatedResponse(
    RoleResource::collection($paginatedData),
    'Optional message'
);
```

#### 4. Resource Response
```php
$this->resourceResponse(
    new RoleResource($role),
    'Resource fetched successfully'
);
```

#### 5. Created Response (201)
```php
$this->createdResponse(
    new RoleResource($role),
    'Role created successfully'
);
```

#### 6. Deleted Response
```php
$this->deletedResponse('Role deleted successfully');
```

#### 7. Not Found Response (404)
```php
$this->notFoundResponse('Resource not found');
```

### Response Format

All responses follow this consistent structure:

**Success Response:**
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field": "validation error"
  }
}
```

**Paginated Response:**
```json
{
  "success": true,
  "data": [ ... ],
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

## Growth Statistics

The `BaseService` class provides reusable growth calculation functionality for both roles and permissions.

### Available Periods

The growth statistics support the following periods:
- `last_7_days` - Last 7 days vs. previous 7 days
- `last_30_days` / `last_month` - Last 30 days vs. previous 30 days
- `last_3_months` - Last 3 months vs. previous 3 months
- `last_6_months` - Last 6 months vs. previous 6 months
- `last_year` - Last year vs. previous year
- `this_week` - Current week vs. previous week
- `this_month` - Current month vs. previous month
- `this_year` - Current year vs. previous year

### Growth Data Structure

Each period returns the following data:

```json
{
  "current": 45,
  "previous": 30,
  "difference": 15,
  "percentage": 50.0,
  "trend": "up"
}
```

**Fields:**
- `current` - Count in the current period
- `previous` - Count in the previous/comparison period
- `difference` - Difference between current and previous (can be negative)
- `percentage` - Percentage change (positive or negative)
- `trend` - One of: `up`, `down`, or `stable`

### Using Growth Statistics

#### 1. Role Statistics with Growth

**Endpoint:** `GET /api/roles/stats`

**Response:**
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
      "last_30_days": {
        "current": 12,
        "previous": 8,
        "difference": 4,
        "percentage": 50.0,
        "trend": "up"
      },
      "last_3_months": {
        "current": 15,
        "previous": 10,
        "difference": 5,
        "percentage": 50.0,
        "trend": "up"
      },
      "last_6_months": {
        "current": 15,
        "previous": 7,
        "difference": 8,
        "percentage": 114.29,
        "trend": "up"
      },
      "last_year": {
        "current": 15,
        "previous": 5,
        "difference": 10,
        "percentage": 200.0,
        "trend": "up"
      }
    }
  }
}
```

#### 2. Permission Statistics with Growth

**Endpoint:** `GET /api/permissions/stats`

**Response:**
```json
{
  "success": true,
  "data": {
    "total": 50,
    "active": 48,
    "deleted": 2,
    "assigned": 45,
    "unassigned": 3,
    "by_group": {
      "users": 12,
      "roles": 8,
      "posts": 15
    },
    "growth": {
      "last_7_days": {
        "current": 3,
        "previous": 2,
        "difference": 1,
        "percentage": 50.0,
        "trend": "up"
      },
      ...
    }
  }
}
```

### Custom Growth Calculations

If you need to use growth statistics in your own services, extend `BaseService`:

```php
namespace App\Services;

use Enadstack\LaravelRoles\Services\BaseService;
use App\Models\YourModel;

class YourService extends BaseService
{
    public function stats(): array
    {
        return [
            'total' => YourModel::count(),
            'growth' => $this->calculateGrowth(YourModel::class, 'created_at'),
        ];
    }
    
    // Custom periods
    public function customGrowth(): array
    {
        return $this->calculateGrowth(
            YourModel::class, 
            'created_at',
            ['last_7_days', 'last_30_days'] // Only specific periods
        );
    }
    
    // Custom query-based growth
    public function customQueryGrowth(): array
    {
        $currentQuery = YourModel::where('status', 'active')
            ->where('created_at', '>=', now()->subDays(7));
            
        $previousQuery = YourModel::where('status', 'active')
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)]);
            
        return $this->calculateCustomGrowth($currentQuery, $previousQuery);
    }
}
```

## Usage in Controllers

### Using ApiResponseTrait

```php
namespace App\Http\Controllers;

use Enadstack\LaravelRoles\Traits\ApiResponseTrait;
use Illuminate\Routing\Controller;

class YourController extends Controller
{
    use ApiResponseTrait;
    
    public function index()
    {
        $data = YourModel::paginate(20);
        return $this->paginatedResponse(
            YourResource::collection($data)
        );
    }
    
    public function store(Request $request)
    {
        $model = YourModel::create($request->validated());
        return $this->createdResponse(
            new YourResource($model),
            'Resource created successfully'
        );
    }
    
    public function show(YourModel $model)
    {
        return $this->resourceResponse(new YourResource($model));
    }
    
    public function destroy(YourModel $model)
    {
        $model->delete();
        return $this->deletedResponse('Resource deleted successfully');
    }
}
```

## Benefits

### 1. Consistency
- All API responses follow the same format
- Easier for frontend developers to consume
- Predictable error handling

### 2. Reusability
- Growth calculation logic is reusable across services
- No code duplication
- Easy to maintain and update

### 3. Flexibility
- Customize periods as needed
- Use custom queries for complex scenarios
- Extend BaseService for your own services

### 4. Performance
- Efficient database queries
- No N+1 query problems
- Minimal overhead

## Frontend Integration Example

```typescript
// Example response handler
interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string>;
}

interface PaginatedResponse<T> extends ApiResponse<T[]> {
  meta: {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
  };
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
}

interface GrowthData {
  current: number;
  previous: number;
  difference: number;
  percentage: number;
  trend: 'up' | 'down' | 'stable';
}

interface RoleStats {
  total: number;
  active: number;
  deleted: number;
  with_permissions: number;
  without_permissions: number;
  growth: Record<string, GrowthData>;
}

// Usage
const response = await fetch('/api/roles/stats');
const data: ApiResponse<RoleStats> = await response.json();

if (data.success) {
  console.log('Total roles:', data.data.total);
  console.log('7-day growth:', data.data.growth.last_7_days);
}
```

## Testing

Example test for growth statistics:

```php
public function test_role_stats_includes_growth_data()
{
    // Create roles in different time periods
    Role::factory()->create(['created_at' => now()->subDays(5)]);
    Role::factory()->create(['created_at' => now()->subDays(10)]);
    Role::factory()->create(['created_at' => now()->subDays(40)]);
    
    $response = $this->getJson('/api/roles/stats');
    
    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'total',
                'growth' => [
                    'last_7_days' => ['current', 'previous', 'difference', 'percentage', 'trend'],
                    'last_30_days' => ['current', 'previous', 'difference', 'percentage', 'trend'],
                ]
            ]
        ]);
}
```


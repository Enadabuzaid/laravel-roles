# Quick Reference: API Response & Growth Statistics

## API Response Methods

```php
// Success (200)
$this->successResponse($data, 'Message');

// Error (400)
$this->errorResponse('Error message', 400, ['errors']);

// Resource (200)
$this->resourceResponse(new Resource($model), 'Message');

// Created (201)
$this->createdResponse(new Resource($model), 'Created');

// Deleted (200)
$this->deletedResponse('Deleted successfully');

// Not Found (404)
$this->notFoundResponse('Not found');

// Paginated (200)
$this->paginatedResponse(Resource::collection($paginated));
```

## Growth Statistics Usage

### In Your Service (Extend BaseService)

```php
class YourService extends BaseService
{
    public function stats(): array
    {
        return [
            'total' => YourModel::count(),
            'growth' => $this->calculateGrowth(YourModel::class),
        ];
    }
}
```

### Available Periods

- `last_7_days`
- `last_30_days` or `last_month`
- `last_3_months`
- `last_6_months`
- `last_year`
- `this_week`
- `this_month`
- `this_year`

### Custom Periods

```php
$growth = $this->calculateGrowth(
    YourModel::class,
    'created_at',
    ['last_7_days', 'last_30_days'] // Only these periods
);
```

### Custom Query Growth

```php
$current = YourModel::where('status', 'active')
    ->where('created_at', '>=', now()->subDays(7));
    
$previous = YourModel::where('status', 'active')
    ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)]);
    
$growth = $this->calculateCustomGrowth($current, $previous);
```

## Response Format

### Standard Success
```json
{
  "success": true,
  "message": "Success",
  "data": { ... }
}
```

### Growth Data Structure
```json
{
  "current": 45,
  "previous": 30,
  "difference": 15,
  "percentage": 50.0,
  "trend": "up"
}
```

## Example API Calls

### Get Stats with Growth
```bash
GET /api/roles/stats
GET /api/permissions/stats
```

### Response Example
```json
{
  "success": true,
  "data": {
    "total": 15,
    "active": 12,
    "deleted": 3,
    "growth": {
      "last_7_days": {
        "current": 5,
        "previous": 3,
        "difference": 2,
        "percentage": 66.67,
        "trend": "up"
      }
    }
  }
}
```

## Implementing in Your Own Controller

```php
use Enadstack\LaravelRoles\Traits\ApiResponseTrait;

class YourController extends Controller
{
    use ApiResponseTrait;
    
    public function index()
    {
        $data = YourModel::paginate();
        return $this->paginatedResponse(
            YourResource::collection($data)
        );
    }
}
```

## Files to Reference

- **Implementation Details**: See `IMPLEMENTATION_SUMMARY_API_GROWTH.md`
- **Full Guide**: See `API_RESPONSE_AND_GROWTH_GUIDE.md`
- **Trait**: `src/Traits/ApiResponseTrait.php`
- **Base Service**: `src/Services/BaseService.php`


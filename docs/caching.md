# Caching

This document explains caching in Laravel Roles.

## Why Caching is Used

Permission checking happens frequently. Caching provides:

- Reduced database queries
- Faster permission checks
- Lower server load
- Better response times

## Cache Configuration

```php
// config/roles.php
'cache' => [
    'enabled' => env('ROLES_CACHE_ENABLED', true),
    'ttl' => 300, // 5 minutes
    'prefix' => 'laravel_roles',
],
```

## Contextual Cache Keys

Cache keys are context-aware to prevent data leaks:

```
{prefix}:{guard}:{tenant_scope}:{key}
```

### Examples

Single tenant, web guard:

```
laravel_roles:web:single:roles_list
laravel_roles:web:single:matrix
```

Team-scoped, team ID 5:

```
laravel_roles:web:team_scoped_5:roles_list
laravel_roles:web:team_scoped_5:matrix
```

Multi-database, tenant "acme":

```
laravel_roles:web:db_acme:roles_list
laravel_roles:web:db_acme:matrix
```

### With Locale (i18n enabled)

```
laravel_roles:web:single:en:permissions_labels
laravel_roles:web:single:ar:permissions_labels
```

## What Gets Cached

| Item | Cache Key Suffix | TTL |
|------|------------------|-----|
| Roles list | `roles_list` | Configured TTL |
| Role by ID | `role_{id}` | Configured TTL |
| Permissions list | `permissions_list` | Configured TTL |
| Grouped permissions | `permissions_grouped` | Configured TTL |
| Permission matrix | `matrix` | Configured TTL |
| Role stats | `roles_stats` | Configured TTL |
| Permission stats | `permissions_stats` | Configured TTL |

## Cache Invalidation

Cache is automatically invalidated on:

### Role Events

| Event | Keys Invalidated |
|-------|------------------|
| Role created | `roles_list`, `roles_stats`, `matrix` |
| Role updated | `roles_list`, `role_{id}`, `matrix` |
| Role deleted | `roles_list`, `roles_stats`, `role_{id}`, `matrix` |

### Permission Events

| Event | Keys Invalidated |
|-------|------------------|
| Permission created | `permissions_list`, `permissions_grouped`, `permissions_stats`, `matrix` |
| Permission updated | `permissions_list`, `permission_{id}`, `matrix` |
| Permission deleted | `permissions_list`, `permissions_grouped`, `permissions_stats`, `matrix` |

### Permission Assignment

| Event | Keys Invalidated |
|-------|------------------|
| Permissions synced | `matrix`, `role_{id}` |
| Diff sync | `matrix`, `role_{id}` |

### Manual Invalidation

```php
use Enadstack\LaravelRoles\Contracts\CacheKeyBuilderContract;

$cache = app(CacheKeyBuilderContract::class);

// Forget specific key
$cache->forget('matrix');

// Flush all package cache (respects context)
$cache->flush();
```

## Disabling Cache

For development or debugging:

```php
// config/roles.php
'cache' => [
    'enabled' => false,
],
```

Or via environment:

```env
ROLES_CACHE_ENABLED=false
```

When disabled, every query hits the database.

## Supported Cache Drivers

The package works with any Laravel cache driver:

| Driver | Tag Support | Notes |
|--------|-------------|-------|
| `array` | No | Development only |
| `file` | No | Works, slower |
| `database` | No | Works |
| `redis` | Yes | Recommended |
| `memcached` | Yes | Recommended |

### Tag Support

If your cache driver supports tags, the package uses them for more efficient invalidation:

```php
// With tags (Redis/Memcached)
Cache::tags(['laravel_roles', 'tenant_5'])->flush();

// Without tags
// Keys are individually forgotten
```

## Cache and Multi-Tenancy

### Team-Scoped Mode

Each team has separate cache entries:

```
laravel_roles:web:team_scoped_1:matrix
laravel_roles:web:team_scoped_2:matrix
```

Changing data for team 1 does not affect team 2's cache.

### Multi-Database Mode

Each tenant database has its own cache scope:

```
laravel_roles:web:db_tenant_a:matrix
laravel_roles:web:db_tenant_b:matrix
```

Cache is naturally isolated.

## Force Refresh

### Via API

```
GET /admin/acl/matrix?refresh=true
GET /admin/acl/roles?refresh=true
```

### Via Artisan

```bash
php artisan cache:clear
```

Note: This clears ALL cache, not just package cache.

## Performance Tips

1. **Use Redis or Memcached** for production
2. **Set appropriate TTL** based on how often permissions change
3. **Don't disable cache in production** without good reason
4. **Monitor cache hit rates** if you have high traffic

## CacheKeyBuilder Contract

For advanced usage:

```php
interface CacheKeyBuilderContract
{
    public function build(string $suffix): string;
    public function tags(): array;
    public function ttl(): int;
    public function isEnabled(): bool;
    public function remember(string $suffix, callable $callback): mixed;
    public function forget(string $suffix): void;
    public function flush(): void;
}
```

## Next Steps

- [Configuration](configuration.md)
- [Tenancy](tenancy.md)
- [Performance Considerations](troubleshooting.md)

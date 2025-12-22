# Tenancy

This document explains the three tenancy modes supported by Laravel Roles.

## Overview

The package supports three tenancy modes through the `TenantContextContract` abstraction:

| Mode | Use Case | Isolation Level |
|------|----------|-----------------|
| `single` | Non-multi-tenant apps | Global data |
| `team_scoped` | Same-database multi-tenancy | Row-level isolation |
| `multi_database` | Separate database per tenant | Database-level isolation |

## Single Tenant Mode

### When to Use

- Standard Laravel applications
- No multi-tenant requirements
- Shared roles across all users

### Configuration

```php
// config/roles.php
'tenancy' => [
    'mode' => 'single',
],
```

### Behavior

- All roles and permissions are global
- No tenant scoping applied
- Cache keys do not include tenant context
- This is the default mode

### Data Isolation

None. All data is shared across the application.

## Team-Scoped Mode

### When to Use

- SaaS applications with teams/organizations
- Shared database across tenants
- Using Spatie's built-in team feature

### Required Setup

1. Enable in package config:

```php
// config/roles.php
'tenancy' => [
    'mode' => 'team_scoped',
    'team_foreign_key' => 'team_id',
],
```

2. Enable Spatie teams:

```php
// config/permission.php
'teams' => true,
'team_foreign_key' => 'team_id',
```

3. Run migrations (adds `team_id` column to roles/permissions tables)

4. Set team context before any ACL operations:

```php
use Spatie\Permission\PermissionRegistrar;

// Set team context (e.g., in middleware)
app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
```

### Behavior

- Roles and permissions are scoped to teams
- Queries automatically filter by team
- Cache keys include team ID
- Team context propagates to Spatie

### Data Isolation

Row-level. Each team sees only its own roles and permissions.

### Common Pitfalls

**Pitfall: Forgetting to set team context**

```php
// Wrong - no team context set
$user->assignRole('admin'); // May fail or assign to wrong team

// Correct - set team first
app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
$user->assignRole('admin');
```

**Pitfall: Mismatched foreign keys**

Ensure both configs use the same key:

```php
// config/roles.php
'team_foreign_key' => 'team_id',

// config/permission.php
'team_foreign_key' => 'team_id', // Must match!
```

## Multi-Database Mode

### When to Use

- Enterprise multi-tenant applications
- Separate database per tenant
- Using stancl/tenancy, spatie/laravel-multitenancy, etc.

### Required Setup

1. Configure the mode:

```php
// config/roles.php
'tenancy' => [
    'mode' => 'multi_database',
    'provider' => 'stancl/tenancy', // or 'spatie/laravel-multitenancy'
],
```

2. The package auto-detects common providers:
   - `stancl/tenancy`
   - `spatie/laravel-multitenancy`
   - `tenancy/tenancy`

3. For custom providers, set a resolver:

```php
use Enadstack\LaravelRoles\Context\MultiDatabaseTenantContext;

$context = app(TenantContextContract::class);
$context->setTenantIdResolver(function () {
    return MyTenancy::current()?->id;
});
```

### Behavior

- Each tenant database has its own roles/permissions tables
- No cross-tenant data access possible
- Cache keys include tenant identifier
- Spatie teams feature NOT used (database handles isolation)

### Data Isolation

Database-level. Complete isolation between tenants.

### Common Pitfalls

**Pitfall: Trying to use teams feature**

In multi-database mode, do NOT enable Spatie teams:

```php
// config/permission.php
'teams' => false, // Must be false for multi-database
```

**Pitfall: Running sync in central context**

```bash
# Wrong - runs in central database
php artisan roles:sync

# Correct - run in tenant context
php artisan tenants:run roles:sync
```

## Comparing Modes

| Feature | Single | Team-Scoped | Multi-Database |
|---------|--------|-------------|----------------|
| Database | Single | Single | Per-tenant |
| Isolation | None | Row-level | Database-level |
| Spatie Teams | No | Yes | No |
| External Package | No | No | Required |
| Cache Keys | Global | Team-aware | Tenant-aware |
| Migration | Standard | +team_id | Per-tenant |

## Switching Modes

Changing modes after data exists requires migration:

1. **Single to Team-Scoped**: Run migration to add team_id, update existing data
2. **Team-Scoped to Multi-Database**: Export per-team, import to tenant databases
3. **Multi-Database to Single**: Not recommended (data loss risk)

## TenantContext Contract

All tenancy logic flows through `TenantContextContract`:

```php
interface TenantContextContract
{
    public function mode(): string;
    public function tenantId(): int|string|null;
    public function scopeKey(): string;
    public function applyToSpatie(): void;
    public function isSingleTenant(): bool;
    public function isTeamScoped(): bool;
    public function isMultiDatabase(): bool;
}
```

You can inject this contract to check tenancy state:

```php
public function __construct(TenantContextContract $tenant)
{
    if ($tenant->isMultiDatabase()) {
        // Multi-database specific logic
    }
}
```

## Next Steps

- [Guards](guards.md)
- [Caching](caching.md) (tenant-aware cache keys)
- [Commands](commands.md) (tenant-aware sync)

# Configuration

This document covers all configuration options for the Laravel Roles package.

## Config File Location

After publishing, the config file is located at:

```
config/roles.php
```

## Configuration Reference

### Guard Configuration

```php
'guard' => env('ROLES_GUARD', 'web'),
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `guard` | string | `'web'` | Default guard for roles and permissions |

The guard must exist in `config/auth.php`. Common values: `web`, `api`, `sanctum`.

### Tenancy Configuration

```php
'tenancy' => [
    'mode' => env('ROLES_TENANCY_MODE', 'single'),
    'team_foreign_key' => 'team_id',
    'provider' => null,
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `mode` | string | `'single'` | One of: `single`, `team_scoped`, `multi_database` |
| `team_foreign_key` | string | `'team_id'` | Foreign key for team-scoped mode |
| `provider` | string|null | `null` | External tenancy provider identifier |

See [Tenancy](tenancy.md) for detailed setup.

### i18n Configuration

```php
'i18n' => [
    'enabled' => env('ROLES_I18N_ENABLED', false),
    'supported_locales' => ['en', 'ar'],
    'fallback_locale' => 'en',
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `false` | Enable multi-language labels |
| `supported_locales` | array | `['en', 'ar']` | Supported locale codes |
| `fallback_locale` | string | `'en'` | Fallback when translation missing |

When enabled, `label` and `description` fields are stored as JSON.

### Routes Configuration

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'admin/acl',
    'middleware' => ['web', 'auth'],
    'expose_me' => true,
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `true` | Register API routes |
| `prefix` | string | `'admin/acl'` | URL prefix for all routes |
| `middleware` | array | `['web', 'auth']` | Middleware applied to routes |
| `expose_me` | bool | `true` | Enable `/me/acl` endpoint |

### Cache Configuration

```php
'cache' => [
    'enabled' => env('ROLES_CACHE_ENABLED', true),
    'ttl' => 300,
    'prefix' => 'laravel_roles',
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `true` | Enable caching |
| `ttl` | int | `300` | Cache TTL in seconds |
| `prefix` | string | `'laravel_roles'` | Cache key prefix |

See [Caching](caching.md) for details.

### UI Configuration

```php
'ui' => [
    'enabled' => env('ROLES_UI_ENABLED', false),
    'driver' => 'vue',
    'prefix' => 'admin/acl',
    'middleware' => ['web', 'auth'],
    'layout' => null,
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `false` | Enable admin UI routes |
| `driver` | string | `'vue'` | UI driver (currently only `vue`) |
| `prefix` | string | `'admin/acl'` | URL prefix for UI pages |
| `middleware` | array | `['web', 'auth']` | Middleware for UI routes |
| `layout` | string|null | `null` | Inertia layout component |

See [Vue UI](ui-vue.md) for setup.

### Seed Configuration

```php
'seed' => [
    'roles' => ['admin', 'editor', 'viewer'],
    'permission_groups' => [
        'users' => ['list', 'create', 'show', 'update', 'delete'],
        'roles' => ['list', 'create', 'show', 'update', 'delete'],
    ],
    'map' => [
        'admin' => ['*'],
        'editor' => ['users.*'],
        'viewer' => ['*.list', '*.show'],
    ],
],
```

| Option | Type | Description |
|--------|------|-------------|
| `roles` | array | Roles to create on sync |
| `permission_groups` | array | Permission groups and actions |
| `map` | array | Role to permission mappings (supports wildcards) |

## Environment Variables

| Variable | Config Key | Default |
|----------|------------|---------|
| `ROLES_GUARD` | `roles.guard` | `web` |
| `ROLES_TENANCY_MODE` | `roles.tenancy.mode` | `single` |
| `ROLES_I18N_ENABLED` | `roles.i18n.enabled` | `false` |
| `ROLES_CACHE_ENABLED` | `roles.cache.enabled` | `true` |
| `ROLES_UI_ENABLED` | `roles.ui.enabled` | `false` |

## Common Mistakes

### Mistake: Wrong guard in config

```php
// Wrong - guard doesn't exist in auth.php
'guard' => 'admin',
```

Run `roles:doctor` to verify guard configuration.

### Mistake: Enabling i18n after data exists

If you enable i18n after creating permissions, the existing string fields will not be migrated to JSON. You must:

1. Export existing data
2. Run the migration
3. Re-import with JSON structure

### Mistake: Team-scoped without Spatie teams enabled

```php
// config/roles.php
'tenancy' => ['mode' => 'team_scoped'],

// config/permission.php - MUST also set:
'teams' => true,
```

Both configs must be aligned.

## Next Steps

- [Tenancy Setup](tenancy.md)
- [Guards](guards.md)
- [Caching](caching.md)

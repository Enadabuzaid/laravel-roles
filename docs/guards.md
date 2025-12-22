# Guards

This document explains guard handling in Laravel Roles.

## Guard Concepts in Laravel

A guard defines how users are authenticated. Laravel supports multiple guards:

```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'token',
        'provider' => 'users',
    ],
],
```

Each guard can have different user providers and authentication drivers.

## How the Package Resolves Guards

The package uses `GuardResolverContract` to determine the active guard:

```php
interface GuardResolverContract
{
    public function guard(): string;
    public function availableGuards(): array;
    public function isValid(string $guard): bool;
    public function defaultGuard(): string;
}
```

### Resolution Order

1. **Explicit override** via `setGuard()`
2. **Package config** `roles.guard`
3. **Laravel default** `auth.defaults.guard`

### Configuration

```php
// config/roles.php
'guard' => 'web', // or 'api', 'admin', etc.
```

## Web vs API Guards

### Web Guard

- Session-based authentication
- CSRF protection
- Browser cookie storage
- Typical for Blade/Inertia applications

```php
'guard' => 'web',
```

### API Guard

- Token-based authentication
- Stateless requests
- Suitable for REST/SPA applications
- Works with Sanctum, Passport, JWT

```php
'guard' => 'api',
```

## Multi-Guard Projects

Many applications use multiple guards. The package supports this.

### Scenario: Admin + API

```php
// config/auth.php
'guards' => [
    'web' => [...],   // Frontend users
    'admin' => [...], // Admin panel
    'api' => [...],   // Mobile app
],
```

### Setting Guard Per-Request

The package resolves guard from config, but you can override:

```php
use Enadstack\LaravelRoles\Contracts\GuardResolverContract;

$resolver = app(GuardResolverContract::class);

// Temporarily use different guard
$resolver->withGuard('admin', function () {
    // All operations use 'admin' guard here
});
```

### Guard-Specific Roles

Roles are guard-specific. A role named "admin" in `web` guard is different from "admin" in `api` guard:

```php
// Two separate roles
Role::create(['name' => 'admin', 'guard_name' => 'web']);
Role::create(['name' => 'admin', 'guard_name' => 'api']);
```

### Filtering by Guard

API endpoints accept a `guard` parameter:

```
GET /admin/acl/roles?guard=api
GET /admin/acl/permissions?guard=admin
GET /admin/acl/matrix?guard=web
```

## Guard and Caching

Cache keys include the guard to prevent collisions:

```
laravel_roles:web:single:roles_list
laravel_roles:api:single:roles_list
```

Different guards have separate cache entries.

## Guard Validation

The package validates guards on role/permission creation:

```php
// Throws exception if 'invalid_guard' not in auth.guards
Role::create([
    'name' => 'admin',
    'guard_name' => 'invalid_guard',
]);
```

Use `roles:doctor` to verify guard configuration.

## Common Issues

### Issue: Guard Mismatch

```php
// User authenticated with 'api' guard
$user = auth('api')->user();

// Role created with 'web' guard
$role = Role::where('name', 'admin')->first();
// guard_name is 'web'

// This fails - guard mismatch
$user->assignRole($role);
```

**Solution**: Ensure roles match the guard of the authenticated user.

### Issue: Multiple Guards, Same Middleware

```php
// routes/api.php
Route::middleware('auth:api')->group(function () {
    // Uses 'api' guard
});

// But package config says 'web'
'guard' => 'web',
```

**Solution**: Set package guard to match your routes.

## Next Steps

- [Roles](roles.md)
- [Permissions](permissions.md)
- [Caching](caching.md)

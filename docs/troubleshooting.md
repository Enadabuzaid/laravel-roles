# Troubleshooting

This document covers common issues and solutions.

## Diagnostics

Always start with the doctor command:

```bash
php artisan roles:doctor
```

This identifies most configuration issues.

## Common Installation Issues

### Issue: Config file not found

**Symptom**: Error about missing config, or default values used unexpectedly.

**Solution**:

```bash
php artisan vendor:publish --provider="Enadstack\LaravelRoles\Providers\RolesServiceProvider" --tag=roles-config
```

### Issue: Migrations not running

**Symptom**: Tables don't exist, or missing columns.

**Solution**:

1. Ensure Spatie migrations ran first
2. Run package migrations

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate

php artisan vendor:publish --tag=roles-migrations
php artisan migrate
```

### Issue: Service provider not loading

**Symptom**: Routes not registered, services not available.

**Solution**: Clear cached config:

```bash
php artisan config:clear
php artisan clear-compiled
composer dump-autoload
```

## Guard Mismatch Issues

### Issue: "There is no permission named X for guard Y"

**Symptom**: Permission check fails even though permission exists.

**Cause**: Permission was created with different guard than user's authentication guard.

**Solution**:

1. Check which guard the permission uses:

```php
$permission = Permission::where('name', 'users.list')->first();
echo $permission->guard_name; // e.g., 'web'
```

2. Ensure user is authenticated with the same guard:

```php
$user = auth('web')->user(); // Must match permission's guard
```

3. Or create permission for the correct guard:

```php
Permission::create([
    'name' => 'users.list',
    'guard_name' => 'api', // Match your auth guard
]);
```

### Issue: Role assignment fails silently

**Cause**: Role guard doesn't match user's guard.

**Solution**: Same as above. Ensure guards match.

### Issue: Multiple guards, wrong one used

**Solution**: Explicitly specify guard:

```bash
# When syncing
php artisan roles:sync --guard=api

# In config
'guard' => 'api',
```

## Tenancy Misconfiguration

### Issue: Team-scoped mode not isolating data

**Cause**: Spatie teams not enabled, or team context not set.

**Solution**:

1. Enable teams in both configs:

```php
// config/roles.php
'tenancy' => ['mode' => 'team_scoped'],

// config/permission.php
'teams' => true,
```

2. Set team context before ACL operations:

```php
app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
```

### Issue: "Team ID is null" errors

**Cause**: Team context not set before Spatie operations.

**Solution**: Set team in middleware:

```php
// app/Http/Middleware/SetTeamContext.php
public function handle($request, $next)
{
    $teamId = $request->user()->team_id;
    app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
    return $next($request);
}
```

### Issue: Multi-database tenant not detected

**Cause**: Provider not recognized or not in tenant context.

**Solution**:

1. Specify provider explicitly:

```php
'tenancy' => [
    'mode' => 'multi_database',
    'provider' => 'stancl/tenancy',
],
```

2. Or set custom resolver:

```php
app(TenantContextContract::class)->setTenantIdResolver(function () {
    return tenant()?->id;
});
```

## Cache-Related Issues

### Issue: Changes not reflected immediately

**Cause**: Cached data not invalidated.

**Solution**:

```bash
php artisan cache:clear
```

Or force refresh via API:

```
GET /admin/acl/roles?refresh=true
```

### Issue: Different tenants seeing each other's data

**Cause**: Cache keys not including tenant context (should not happen in v1.3.0+).

**Solution**: Clear cache and verify tenancy mode:

```bash
php artisan cache:clear
php artisan roles:doctor
```

### Issue: Cache errors with certain drivers

**Cause**: Driver doesn't support required operations.

**Solution**: Use a supported driver for production:

```php
// Recommended
CACHE_DRIVER=redis

// Or
CACHE_DRIVER=memcached
```

## UI Not Showing Issues

### Issue: UI routes return 404

**Cause**: UI not enabled or wrong driver.

**Solution**:

```php
// config/roles.php
'ui' => [
    'enabled' => true,
    'driver' => 'vue',
],
```

Then clear config:

```bash
php artisan config:clear
php artisan route:clear
```

### Issue: UI pages show blank

**Cause**: Inertia not configured, or Vue components not compiled.

**Solution**:

1. Ensure Inertia is installed:

```bash
composer require inertiajs/inertia-laravel
npm install @inertiajs/vue3
```

2. Publish Vue components:

```bash
php artisan vendor:publish --tag=laravel-roles-vue
```

3. Compile assets:

```bash
npm run dev
```

### Issue: shadcn-vue components not found

**Cause**: Components not installed.

**Solution**: Install required components:

```bash
npx shadcn-vue@latest add button card table ...
```

See [Vue UI](ui-vue.md) for full list.

## API Issues

### Issue: 401 Unauthorized on all routes

**Cause**: Authentication middleware not satisfied.

**Solution**: Ensure user is authenticated before accessing routes:

```php
// Login first
$this->postJson('/login', $credentials);

// Then access ACL routes
$this->getJson('/admin/acl/roles');
```

### Issue: 403 Forbidden

**Cause**: User doesn't have required permissions.

**Solution**: The package uses Laravel's authorization. Check if user has permission or if Gate is blocking.

### Issue: Validation errors on create

**Cause**: Required fields missing or invalid.

**Solution**: Check required fields:

- Role: `name`, `guard_name`
- Permission: `name`, `guard_name`

## Performance Issues

### Issue: Slow matrix endpoint

**Cause**: Large number of roles/permissions without caching.

**Solution**:

1. Enable caching:

```php
'cache' => ['enabled' => true],
```

2. Use pagination if supported by your UI

### Issue: High database load

**Cause**: Cache disabled or expired frequently.

**Solution**:

1. Increase TTL:

```php
'cache' => ['ttl' => 3600], // 1 hour
```

2. Use a fast cache driver (Redis/Memcached)

## Still Having Issues?

1. Run `roles:doctor` and review output
2. Check Laravel logs: `storage/logs/laravel.log`
3. Enable query logging to see database queries
4. Check GitHub issues for similar problems

## Next Steps

- [Configuration](configuration.md)
- [Commands](commands.md)
- [Testing](testing.md)

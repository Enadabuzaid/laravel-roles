# Upgrading

This document covers upgrading from previous versions.

## Upgrading from v1.2.x to v1.3.0

v1.3.0 is a significant release with new features and internal architecture changes.

### Step 1: Update Composer

```bash
composer require enadstack/laravel-roles:^1.3
```

### Step 2: Publish New Migrations

```bash
php artisan vendor:publish --tag=roles-migrations
```

New migrations add:

- `status` column to roles and permissions
- Extended fields for soft deletes (if not already present)

### Step 3: Run Migrations

```bash
php artisan migrate
```

### Step 4: Update Configuration (Optional)

Review your `config/roles.php`. New options in v1.3.0:

```php
// New UI configuration section
'ui' => [
    'enabled' => false,
    'driver' => 'vue',
    'prefix' => 'admin/acl',
    'middleware' => ['web', 'auth'],
],
```

If you have a custom config, merge the new sections manually.

### Step 5: Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Step 6: Verify Installation

```bash
php artisan roles:doctor
```

## Behavior Changes in v1.3.0

### TenantContext Abstraction

All tenancy logic now flows through `TenantContextContract`. If you were directly calling Spatie's team methods, you should now use:

```php
// Old (still works, but not recommended)
app(PermissionRegistrar::class)->setPermissionsTeamId($id);

// New (recommended)
app(TenantContextContract::class)->setTenantId($id);
```

### GuardResolver

Guard resolution is now abstracted. If you were accessing guards directly:

```php
// Old
config('roles.guard');

// New (recommended for consistency)
app(GuardResolverContract::class)->guard();
```

### Permission Sync

The new diff-based sync is recommended over full sync:

```php
// Old - replaces all permissions
$role->syncPermissions($permissions);

// New - adds/removes only what changed
$syncService->diffSync($role, [
    'grant' => ['new.permission'],
    'revoke' => ['old.permission'],
]);
```

Full sync still works for backward compatibility.

### Cache Keys

Cache keys now include tenant and guard context:

```
// Old format
laravel_roles:roles_list

// New format
laravel_roles:web:single:roles_list
```

Cache is automatically cleared on upgrade. No manual action needed.

## Breaking Changes

### None

v1.3.0 maintains full backward compatibility with v1.2.x.

All existing:

- API endpoints work unchanged
- Model methods work unchanged
- Events work unchanged
- Config structure is compatible

## Notable Additions

### New API Endpoints

- `POST /roles/{id}/permissions/diff` - Diff-based permission sync
- `GET /matrix` - Permission matrix
- `GET /matrix/grouped` - Grouped permission matrix
- `GET /me/acl` - Combined current user ACL

### New Commands

- `roles:doctor` - Configuration diagnostics

### New Services

- `RolePermissionSyncServiceContract` - Diff sync with wildcards
- `PermissionMatrixServiceContract` - Efficient matrix building
- `TenantContextContract` - Tenant abstraction
- `GuardResolverContract` - Guard resolution
- `CacheKeyBuilderContract` - Contextual caching

### UI Support

Optional Vue-based admin UI. See [Vue UI](ui-vue.md).

## Upgrade Checklist

- [ ] Composer updated to ^1.3
- [ ] New migrations published and run
- [ ] Config reviewed for new options
- [ ] Caches cleared
- [ ] `roles:doctor` passes
- [ ] Tests pass (if you have custom tests)
- [ ] UI enabled (if desired)

## Rollback

If you need to rollback:

```bash
composer require enadstack/laravel-roles:^1.2

# Rollback migrations if needed
php artisan migrate:rollback --step=N

php artisan config:clear
php artisan cache:clear
```

## Getting Help

If you encounter issues:

1. Check [Troubleshooting](troubleshooting.md)
2. Run `roles:doctor` for diagnostics
3. Check GitHub issues

## Next Steps

- [Installation](installation.md) (for new installations)
- [Configuration](configuration.md)
- [Vue UI](ui-vue.md) (new feature)

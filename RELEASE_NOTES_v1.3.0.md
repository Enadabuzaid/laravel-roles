# Laravel Roles v1.3.0 Release Notes

## Summary

Laravel Roles v1.3.0 is a major release introducing a complete backend architecture redesign. This version brings contract-based abstractions, full multi-tenancy support, efficient permission matrix handling, and an optional Vue admin UI.

The release maintains full backward compatibility with v1.2.x while adding powerful new features for enterprise applications.

## Added

### Backend Architecture
- **TenantContextContract** - Complete tenant abstraction supporting single, team-scoped, and multi-database modes
- **GuardResolverContract** - Guard resolution with validation and override support
- **CacheKeyBuilderContract** - Contextual cache keys (tenant, guard, locale aware)
- **RolePermissionSyncServiceContract** - Diff-based permission sync with wildcard support
- **PermissionMatrixServiceContract** - Efficient matrix building with no N+1 queries

### API Endpoints
- `POST /roles/{id}/permissions/diff` - Grant/revoke permissions with wildcards
- `GET /matrix` - Permission matrix for all roles
- `GET /matrix/grouped` - Grouped permission matrix
- `GET /me/acl` - Current user's roles and permissions
- `GET /roles/stats` and `GET /permissions/stats` - Statistics endpoints

### Commands
- `roles:doctor` - Configuration diagnostics and health check
- Enhanced `roles:install` with interactive setup
- Enhanced `roles:sync` with dry-run and team-id options

### Vue UI (Optional)
- Complete Vue 3 admin UI with Inertia.js and shadcn-vue
- Roles CRUD pages with search and filtering
- Permission matrix with optimistic updates
- Reusable UI components
- TypeScript support
- Publishable assets

### Testing
- 206 test methods across unit and feature tests
- Test traits for tenancy and guard configuration
- Performance tests with query count assertions
- Configuration matrix tests (6 guard/tenancy combinations)

### Documentation
- Complete documentation in `/docs/`
- 14 documentation files covering all features
- Feature roadmap with version planning

## Changed

- All tenancy logic flows through `TenantContextContract`
- All guard resolution flows through `GuardResolverContract`
- Cache keys now include tenant, guard, and locale context
- Permission matrix uses maximum 5 queries regardless of data size

## Fixed

- Cache key collisions in multi-tenant environments
- N+1 queries in permission matrix endpoint
- Guard mismatch issues in team-scoped mode

## Backward Compatibility

v1.3.0 is **fully backward compatible** with v1.2.x:
- All existing API endpoints work unchanged
- All model methods work unchanged
- All events work unchanged
- Configuration structure is compatible

## Requirements

- PHP 8.2+
- Laravel 12.0+
- spatie/laravel-permission 6.0+

## Installation

```bash
composer require enadstack/laravel-roles:^1.3
php artisan roles:install
php artisan roles:sync
```

## Upgrading from v1.2.x

```bash
composer require enadstack/laravel-roles:^1.3
php artisan vendor:publish --tag=roles-migrations
php artisan migrate
php artisan config:clear
php artisan cache:clear
```

See [Upgrading Documentation](docs/upgrading.md) for details.

## Documentation

Full documentation available at:
- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Tenancy](docs/tenancy.md)
- [API Reference](docs/api.md)
- [Vue UI](docs/ui-vue.md)
- [All Documentation](docs/)

## Contributors

- Enad Abuzaid (@enadabuzaid)

## License

MIT License

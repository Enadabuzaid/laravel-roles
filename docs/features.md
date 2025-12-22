# Feature Roadmap

This document tracks completed features, planned work, and ideas for future versions.

## Completed (v1.3.0)

### Backend Architecture

- [x] Contract-based architecture with dependency injection
- [x] TenantContextContract for tenant abstraction
- [x] GuardResolverContract for guard resolution
- [x] CacheKeyBuilderContract for contextual caching
- [x] RoleServiceContract and PermissionServiceContract
- [x] RolePermissionSyncServiceContract with diff-based sync
- [x] PermissionMatrixServiceContract with efficient queries

### Tenancy Support

- [x] Single tenant mode (default)
- [x] Team-scoped mode (Spatie teams integration)
- [x] Multi-database mode (external provider support)
- [x] Automatic provider detection (stancl/tenancy, spatie/laravel-multitenancy, tenancy/tenancy)
- [x] Custom tenant resolver support
- [x] Tenant-aware cache keys

### Guard Support

- [x] Configurable default guard
- [x] Guard validation on role/permission creation
- [x] Guard filtering in API endpoints
- [x] Guard-aware cache keys
- [x] Multi-guard project support

### Permission Management

- [x] Group-based permission organization
- [x] Wildcard support (* and group.*)
- [x] Diff-based permission sync (grant/revoke)
- [x] Full permission sync (backward compatible)
- [x] Permission matrix endpoint
- [x] Grouped matrix endpoint

### Caching

- [x] Contextual cache keys (tenant/guard/locale)
- [x] Automatic cache invalidation on changes
- [x] Cache tag support (for Redis/Memcached)
- [x] Configurable TTL
- [x] Force refresh option

### Internationalization

- [x] Multi-language labels and descriptions
- [x] JSON storage for translatable fields
- [x] Locale-aware cache keys
- [x] Fallback locale support

### API

- [x] RESTful endpoints for roles and permissions
- [x] Permission diff endpoint
- [x] Matrix endpoints
- [x] Current user ACL endpoint (/me/acl)
- [x] Statistics endpoints
- [x] Soft delete and restore

### Vue UI

- [x] Inertia.js integration
- [x] shadcn-vue component usage
- [x] Roles CRUD pages
- [x] Permission matrix page
- [x] Optimistic updates with rollback
- [x] Group-level permission toggling
- [x] Reusable UI components
- [x] API client layer
- [x] TypeScript support
- [x] Publishable assets

### Commands

- [x] roles:install - Interactive setup
- [x] roles:sync - Config-based synchronization
- [x] roles:doctor - Diagnostics and health check

### Testing

- [x] Base TestCase with Orchestra Testbench
- [x] Reusable test traits for tenancy/guard setup
- [x] Unit tests for all core services
- [x] Feature tests for CRUD operations
- [x] Performance tests with query assertions
- [x] Cache invalidation tests
- [x] Configuration matrix tests
- [x] Upgrade safety tests

### Documentation

- [x] Comprehensive documentation in /docs
- [x] Installation guide
- [x] Configuration reference
- [x] API reference
- [x] Troubleshooting guide
- [x] Upgrade guide

## Planned (v1.4.x)

### Backend

- [ ] Role cloning with permission inheritance
- [ ] Permission import/export (JSON/YAML)
- [ ] Global roles (available across all teams in team-scoped mode)
- [ ] Role hierarchy/inheritance
- [ ] Permission dependencies (require other permissions)

### UI

- [ ] React UI driver
- [ ] Blade UI driver (no JavaScript)
- [ ] Dark mode support
- [ ] User assignment page
- [ ] Activity log viewer
- [ ] Permission usage report

### Integration

- [ ] Audit log integration (spatie/laravel-activitylog)
- [ ] Policy generator from permissions
- [ ] Laravel Nova integration
- [ ] Filament integration

### Performance

- [ ] Bulk operations API
- [ ] Streaming responses for large datasets
- [ ] Query optimization for 1000+ roles

### Security

- [ ] Rate limiting presets
- [ ] IP allowlist for management routes
- [ ] Two-factor authentication for sensitive operations

## Ideas / Research

### Visual Tools

- Visual permission heatmap showing usage patterns
- Interactive permission graph (which roles have which permissions)
- Role comparison tool (diff between two roles)

### Automation

- AI-assisted permission suggestions based on role name
- Auto-generate permissions from route definitions
- Auto-assign permissions based on user behavior

### Enterprise Features

- Organization-level roles (above team level)
- Role templates for quick team setup
- Scheduled role activation/deactivation
- Permission request workflow (users request, admins approve)

### Developer Experience

- Artisan make:role and make:permission generators
- VS Code extension for permission autocomplete
- GraphQL API as alternative to REST

### Multi-Application

- Shared permission registry across applications
- SSO integration for centralized ACL
- Microservices permission synchronization

## Deprecated / Rejected

### Rejected: Direct Spatie Calls in Controllers

**Why rejected**: Violates abstraction principle. All access should go through package services for consistent tenant/guard handling.

### Rejected: Permission caching per-user

**Why rejected**: Memory inefficient. Role-based caching is more effective. Per-user caching would multiply cache entries by user count.

### Rejected: Automatic permission discovery from gates

**Why rejected**: Too "magic". Explicit permission definition is clearer and more maintainable.

### Deprecated: Full permission sync as primary method

**Why deprecated**: Diff-based sync is more efficient and safer for production. Full sync remains for backward compatibility but is not recommended.

## Contributing

Want to help with planned features?

1. Check existing GitHub issues
2. Discuss approach before implementing
3. Follow existing code patterns
4. Include tests with your PR
5. Update relevant documentation

## Version Policy

- **Minor versions (1.4, 1.5)**: New features, no breaking changes
- **Patch versions (1.3.1, 1.3.2)**: Bug fixes only
- **Major versions (2.0)**: May include breaking changes with upgrade guide

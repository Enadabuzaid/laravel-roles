# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.1] - 2025-12-23

### Fixed

- **Vue UI Import Paths**: All composables and pages now correctly use `@/laravel-roles` namespace
- **Component Publish Structure**: UI components are now published to `components/ui/` subfolder to match import paths
- **Missing Components**: Added all components (ViewToggle, FiltersBar, RoleGrid, etc.) to the publish configuration

### Changed

- **Simplified Publish Tag**: `--tag=roles-vue` now publishes everything needed for a complete UI setup
- **Documentation**: Added comprehensive Vite alias setup instructions to `docs/ui-vue.md`

### Important

Users must add the `@/laravel-roles` alias to their `vite.config.ts`:

```typescript
resolve: {
    alias: {
        '@': path.resolve(__dirname, './resources/js'),
        '@/laravel-roles': path.resolve(__dirname, './resources/js/laravel-roles'),
    },
},
```

## [1.3.0] - 2025-12-22

### Added

#### Backend Architecture
- **TenantContextContract**: Complete tenant abstraction for all three tenancy modes
  - `SingleTenantContext`: For non-multi-tenant applications
  - `TeamScopedTenantContext`: For Spatie's team-scoped mode
  - `MultiDatabaseTenantContext`: For external providers (stancl/tenancy, spatie/laravel-multitenancy, tenancy/tenancy)
- **GuardResolverContract**: Guard abstraction with validation and override support
- **CacheKeyBuilderContract**: Contextual cache key generation (tenant, guard, locale aware)
- **RolePermissionSyncServiceContract**: Diff-based permission sync with wildcard support
- **PermissionMatrixServiceContract**: Efficient matrix building with maximum 5 queries

#### API Endpoints
- `POST /roles/{id}/permissions/diff`: Diff-based permission grant/revoke
- `GET /matrix`: Permission matrix for all roles
- `GET /matrix/grouped`: Grouped permission matrix
- `GET /me/acl`: Combined current user roles and permissions
- `GET /roles/stats`: Role statistics
- `GET /permissions/stats`: Permission statistics

#### Commands
- `roles:doctor`: Configuration diagnostics and health check
- Enhanced `roles:install` with interactive setup
- Enhanced `roles:sync` with dry-run and team-id options

#### Vue UI
- Complete Vue 3 admin UI with Inertia.js and shadcn-vue
- `RolesIndex.vue`: List, search, filter, and manage roles
- `RoleCreate.vue`: Create role with initial permissions
- `RoleEdit.vue`: Edit role with tabbed interface
- `PermissionMatrix.vue`: Toggle permissions with optimistic updates
- Reusable UI components (PageHeader, ConfirmDialog, SearchInput, etc.)
- API client layer with TypeScript support
- Vue composables for reactive state management

#### Testing
- 206 test methods across unit and feature tests
- Test traits for tenancy and guard configuration
- Performance tests with query count assertions
- Cache invalidation tests
- Configuration matrix tests (6 guard/tenancy combinations)
- Upgrade safety tests

#### Documentation
- Complete documentation in `/docs/`
- 14 documentation files covering all features
- Feature roadmap with planned/completed tracking
- QA checklist for release validation

### Changed

- All tenancy logic now flows through `TenantContextContract`
- All guard resolution now flows through `GuardResolverContract`
- Cache keys now include tenant, guard, and locale context
- Permission matrix uses ≤5 queries regardless of data size

### Fixed

- Cache key collisions in multi-tenant environments
- N+1 queries in permission matrix endpoint
- Guard mismatch issues in team-scoped mode

### Backward Compatibility

v1.3.0 is fully backward compatible with v1.2.x:
- All existing API endpoints work unchanged
- All model methods work unchanged
- All events work unchanged
- Configuration structure is compatible

## [1.2.2] - 2025-11-15

### Fixed
- Minor bug fixes in permission service
- Improved error messages in sync command

## [1.2.1] - 2025-11-01

### Fixed
- Fixed issue with team_id column in migrations
- Improved Spatie teams integration

## [1.2.0] - 2025-10-15

### Added
- Soft deletes for roles and permissions
- Status field (active/inactive)
- Extended i18n support

### Changed
- Refactored service layer
- Improved migration structure

## [1.1.0] - 2025-10-01

### Added
- i18n support for labels and descriptions
- Team-scoped tenancy mode
- Permission groups

### Changed
- Updated minimum Laravel version to 12.0
- Updated minimum PHP version to 8.2

## [1.0.0] - 2025-09-15

### Added
- Initial release
- Role and Permission CRUD
- Integration with spatie/laravel-permission
- Basic API endpoints
- Artisan sync command

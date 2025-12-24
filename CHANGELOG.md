# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.6] - 2025-12-24

### 🔧 Critical Route Fix & Installation Enhancements

This release fixes a critical issue where UI routes were intercepting API calls, causing JSON endpoints to return HTML responses.

### Fixed

#### Route Conflict Resolution
- **UI/API Route Separation** - UI routes now use `/ui` sub-prefix to prevent conflicts
  - UI routes: `/admin/acl/ui/roles`, `/admin/acl/ui/permissions`, etc.
  - API routes: `/admin/acl/roles`, `/admin/acl/permissions`, etc. (unchanged)
- **JSON API Endpoints** - All API endpoints now correctly return JSON data
- **Stats Endpoints** - `/roles-stats`, `/permissions-stats` work correctly
- **Recent Data Endpoints** - `/roles-recent`, `/permissions-recent` return proper JSON

#### Vue Page Fixes
- All pages now use `apiPrefix` for API calls and `uiPrefix` for navigation
- Fixed data fetching in dashboard pages (stats, recent items)
- Fixed pagination returning HTML instead of JSON
- Added container wrapper to all pages for consistent layout

### Added

#### Install Command Enhancements (`php artisan roles:install`)
- **UI Configuration Prompts** - Enable/disable UI during installation
- **Layout Configuration** - Specify your app's layout component name (e.g., `AppLayout`)
- **Route Prefix Configuration** - Customize the UI route prefix
- **Next Steps Guide** - Shows clear instructions after installation

#### Config Improvements
- Added `ui.layout` configuration option for specifying AppLayout component
- Backend controllers now pass both `prefix` (UI) and `apiPrefix` (API) to Vue pages

### Changed

- Documentation updated to reflect new route architecture
- Vue pages refactored for cleaner API/UI separation

### Migration Guide

If upgrading from v1.3.5:

1. **Update Routes** - UI is now at `/admin/acl/ui/` instead of `/admin/acl/`
2. **Re-publish Vue Pages** - Run `php artisan vendor:publish --tag=roles-vue --force`
3. **Clear Caches** - Run `php artisan config:clear && php artisan route:clear`

## [1.3.5] - 2025-12-24

### 🎨 Complete UI Redesign (Major Update)

The package UI has been completely redesigned with a professional admin dashboard structure:

### Added

#### RolesManagement Dashboard (`/admin/acl/`)
- **Stats Cards** - Total Roles, Total Permissions, With Permissions, Trashed
- **Quick Actions** - Create Role, Open Matrix buttons
- **Action Cards** - Navigate to Roles, Permissions, Matrix pages
- **Recent Roles** - List with edit/delete/restore actions

#### Roles Index (`/admin/acl/roles`)
- **List/Grid View Toggle** - Switch between table and card views
- **Search** - Filter roles by name
- **Guard Filter** - web/api
- **Trashed Filter** - Active Only, With Trashed, Only Trashed
- **Bulk Selection** - Checkbox for bulk delete
- **Pagination** - Full pagination with page controls
- **Actions** - Edit, Delete, Restore per role

#### Role Create (`/admin/acl/roles/create`)
- **Form Validation** - Name, Description, Guard fields
- **Permission Selection** - Grouped with search filter
- **Group Toggle** - Select all permissions in a group

#### Role Edit (`/admin/acl/roles/{id}/edit`)
- **Tabbed Interface** - Details tab + Permissions tab
- **Live Permission Sync** - Changes sync immediately to backend
- **Role Metadata** - Created, Updated dates, Users count

#### PermissionsManagement Dashboard (`/admin/acl/permissions-management`)
- **Stats Cards** - Total Permissions, Groups, Total Roles
- **Action Cards** - Navigate to Permissions, Matrix, Roles

#### Permissions Index (`/admin/acl/permissions`)
- **List/Grid View Toggle** - Table or grouped card view
- **Filters** - Search, Group, Guard
- **Grouped Display** - Permissions organized by group

#### Permission Matrix (`/admin/acl/matrix`)
- **Role Selector Tabs** - Click to switch roles with permission counts
- **Group Toggles** - Toggle all permissions in a group
- **Individual Toggles** - Toggle single permissions with optimistic updates
- **Quick Stats** - Role count, Permission count, Group count

#### Shared Components
- **StatsCard** - Clickable stats card with icon
- **ActionCard** - Navigation card with badge
- **ViewToggle** - List/Grid view switcher
- **ConfirmDialog** - Confirmation modal
- **Pagination** - Pagination controls
- **Toast** - Global toast notifications

### Changed

- **New organized folder structure**:
  ```
  Pages/LaravelRoles/
  ├── RolesManagement/           # Roles dashboard
  │   ├── Index.vue
  │   ├── partials/
  │   │   ├── QuickActions.vue
  │   │   └── RecentRoles.vue
  │   └── Roles/                 # Roles CRUD
  │       ├── Index.vue
  │       ├── Create.vue
  │       └── Edit.vue
  ├── PermissionsManagement/     # Permissions dashboard
  │   ├── Index.vue
  │   ├── partials/
  │   │   └── RecentPermissions.vue
  │   ├── Permissions/
  │   │   └── Index.vue
  │   └── PermissionMatrix/
  │       └── Index.vue
  └── shared/                    # Reusable components
      ├── StatsCard.vue
      ├── ActionCard.vue
      ├── ViewToggle.vue
      ├── ConfirmDialog.vue
      ├── Pagination.vue
      └── Toast.vue
  ```
- **Works with host AppLayout** - Pages integrate with your application layout
- **Consistent design language** - All pages follow modern admin dashboard patterns
- **Simplified publishing** - Single `--tag=roles-vue` publishes everything

### Fixed

- **Removed duplicate pages** - Clean, organized structure
- **API endpoints** - All CRUD operations work with backend
- **Pagination** - Fully functional with page controls
- **Trashed filter** - Proper handling of soft-deleted roles
- **Permission sync** - Live sync by permission name (not IDs)

## [1.3.4] - 2025-12-23

### ⚡ Self-Contained UI (Major Enhancement)

The package now ships a **fully self-contained Vue UI** that requires NO host app dependencies:

- No shadcn-vue installation required
- No Vite alias configuration needed
- No Inertia page resolver changes needed
- Works in any fresh Laravel + Inertia + Vue project

### Added

- **Self-contained UI primitives** (`resources/js/laravel-roles/ui/`):
  - LrButton, LrInput, LrCard, LrTable, LrBadge, LrSwitch, LrCheckbox
  - LrSelect, LrDialog, LrToast, LrSkeleton
  - All use pure Tailwind CSS with CSS variables for theming

- **LaravelRolesLayout** - Package-provided layout with header, navigation, and toast container

- **New self-contained pages**:
  - RolesIndex - with stats cards, search, filters, pagination
  - RoleCreate - form with permission selection
  - RoleEdit - tabbed interface for details and permissions
  - PermissionsIndex - grouped view with stats
  - PermissionMatrix - role tabs, group toggles, optimistic updates

- **New publish tag**: `--tag=roles-vue-standalone` for the self-contained UI

### Fixed

- **401 Unauthorized on UI routes** - Middleware properly defaults to `['web', 'auth']`
- **302 redirects on create/edit** - All UI routes correctly registered
- **Vue SFC parse errors** - All components are valid Vue SFCs
- **Missing component imports** - Self-contained UI has no external dependencies

### Changed

- **Two UI installation options**:
  1. `roles-vue-standalone` (Recommended) - Self-contained, works out of the box
  2. `roles-vue` (Legacy) - For users with existing shadcn-vue setup

- **Documentation rewritten** - Clear step-by-step guide for both installation options

### UI Features

- ✅ Consistent PageHeader component
- ✅ Stats cards (roles + permissions)
- ✅ Search and filter UX
- ✅ Confirm dialogs for destructive actions
- ✅ Toast notifications
- ✅ Loading states and empty states
- ✅ Optimistic updates on matrix toggles
- ✅ Responsive design

### Acceptance Criteria Met

- ✅ Fresh Laravel + Inertia + Vue works without Vite errors
- ✅ No redirects except expected login redirects
- ✅ No 401 on UI pages when logged in
- ✅ All UI pages work end-to-end
- ✅ i18n on/off works without breaking UI



## [1.3.3] - 2025-12-23

### Fixed

- **roles:sync schema-aware**: Command now checks if metadata columns exist before updating, preventing SQL errors on databases without `label`, `description`, or `group_label` columns
- **SQLite compatibility**: New migration uses TEXT columns instead of JSON for full SQLite support
- **JSON encoding**: Arrays are now properly encoded to JSON strings before database storage
- **UI routes**: All create/edit/show routes now fully registered for both roles and permissions
- **Default middleware**: Changed default from `['api', 'auth']` to `['web', 'auth']` for session-based apps (Inertia/Blade)
- **PermissionStatsCards.vue**: Fixed corrupted Vue SFC (was reversed/malformed)
- **ViewToggle.vue**: Removed dependency on `toggle-group` (not standard shadcn-vue), now uses Button components
- **Component imports**: All package components now use correct `@/laravel-roles/types` namespace

### Added

- **Permission metadata migration**: New migration `2025_12_23_000000_add_permission_metadata_columns.php` safely adds nullable `label`, `description`, and `group_label` columns
- **PermissionUIController**: Full UI controller for permission management pages
- **Enhanced roles:doctor diagnostics**: Now reports detailed metadata column status with visual indicators
- **Comprehensive troubleshooting guide**: New `docs/troubleshooting.md` with solutions for common issues
- **UIRoutesTest**: Tests for UI route registration, middleware, and authentication behavior

### Changed

- **UI pages support host AppLayout integration**: Documentation now explains how to wrap package pages with your app's layout
- **Step-by-step UI setup guide**: Completely rewritten `docs/ui-vue.md` with 14-step installation guide
- **Improved error handling**: roles:sync now gracefully skips metadata updates if columns don't exist
- **Config documentation**: Added comments explaining middleware options for session vs API mode

### Migration Notes

For users upgrading from v1.3.0-v1.3.2:

1. Run migrations to add metadata columns:
   ```bash
   php artisan migrate
   ```

2. Verify with doctor:
   ```bash
   php artisan roles:doctor
   ```

3. If using API middleware, update your config:
   ```php
   // config/roles.php
   'routes' => [
       'middleware' => ['api', 'auth:sanctum'], // For API-only mode
   ],
   ```

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

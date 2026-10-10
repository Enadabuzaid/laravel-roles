# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.0.0-alpha.8 — 2026-10-10

### Added
- `lock_reason` on `GET matrix` roles: `owner`, `no_permission` (the user may not update roles), `held` (a role the user holds and is not an Owner) or null, in that priority. `locked` stays exactly `lock_reason !== null`.
- `users` (`view`, `update`, `suspend`, `delete`) and `invitations` (`view`, `create`, `revoke`) ability groups in `GET me`; all false when the matching module is off.

## 2.0.0-alpha.7 — 2026-10-10

### Added
- `HasAccess` deleting/deleted hooks (`UserDeletionGuard`): a direct `$user->delete()` is refused for the last active owner (`LastOwnerDeletionException`, `guardrail.last_owner`) and otherwise cleans the access rows, revokes sent invitations and audits once.
- Arabic and English texts for guardrail, invitation, transition, saved-view and actor-required messages (`access::guardrail`, `access::errors`).
- `invitations-require-users` doctor check (invitation routes need the users module); `PasswordReset` listener stamping `password_changed_at`.

### Changed
- `TargetAboveActorRule` also covers role removal (remove, or sync that removes); `abilities.assign` is false for `above` rows.
- Accept answers 410 when the invited role was trashed or deleted; trashing or deleting a role revokes its pending invitations (`invitations.revoked_role_removed`); resend stamps the resender as the inviter; trash and force delete take the super-role lock first.
- `owner-exists` counts active owners; `roles:owner` reactivates a suspended or deactivated target (invited targets are refused); reactivate, unlock and forced reset of an owner are audited `critical`.
- Removed the unused `UserStatus::isFinal()`.

## 2.0.0-alpha.6 — 2026-10-10

### Added
- Users module (users list, overview, profile, effective permissions, lifecycle actions, invitations, saved views) with the host integration steps: publish migrations, `roles:backfill-users`, append `access.active` to the web group, point `roles.users.accept_url` at a guest page that posts to `invitations/accept`.
- Users module foundation: `user_access_states`, `access_invitations` and `access_saved_views` tables (published by `roles:install` / `vendor:publish --tag=roles-migrations` after the roles/permissions extension stubs, no foreign keys), the `UserStatus` enum and the `UserAccessState`, `AccessInvitation` and `SavedView` models.
- `HasAccess::accessState()`, `accessStatus()` (Active when no row exists) and `isAccessActive()`; the `AccessUser` contract is unchanged.
- `roles:backfill-users` (chunked, idempotent, `--dry-run`) and the `users-state` doctor check (WARN for users without a state row, FAIL when `modules.users` is on and the table is missing).
- `roles.users` config section (`mfa_enabled_column`, `invitation_ttl_hours`, `default_status`, `accept_url`, `saved_view_pages`, reserved `lockout`).

- Guardrails for the user lifecycle: `OwnerCounter` counts only ACTIVE owners (`isActiveOwner()` added), the last-owner rule uses it, and the new `TargetAboveActorRule` (`prevent_target_above_actor`, code `guardrail.target_above_actor`) sits right after the self-destructive rule.
- Login enforcement: `BlockInactiveUser` listener (translatable `access::auth.inactive`) and the `access.active` middleware (ends the session, 403 `account_inactive` for JSON).

- User lifecycle: `POST users/{user}/suspend|reactivate|deactivate|unlock|force-password-reset` and `DELETE users/{user}` backed by the `SuspendUser`, `ReactivateUser`, `DeactivateUser`, `UnlockUser`, `ForcePasswordReset` and `DeleteUser` Actions (transaction, super-role lock, guardrails, one audit entry after commit; no-ops write nothing; illegal transitions are 409 `user.invalid_transition`). Suspend, deactivate, delete and force-password-reset end database sessions and API tokens (any `tokens()` relation, best effort). Force reset sends the link through the broker of the package guard's provider, audits and returns the broker status (`link_sent`, `broker_status`), clears `password_changed_at` and does not change the password. Delete maps a host foreign key violation to 409 `user.in_use`. Reactivate clears the lockout and keeps `activated_at`; state rows are created with insert-or-ignore. New operation types `ReactivateUser`, `UnlockUser`, `ForcePasswordReset` covered by `TargetAboveActorRule`.
- `RecordLastLogin` listener on `Login` (last login time and IP, failed-login counter reset; never throws into the login flow, fails open without the state table).

- Invitations: `GET/POST invitations`, `POST invitations/{invitation}/resend`, `DELETE invitations/{invitation}` and the public `POST invitations/accept` (`CreateInvitation`, `ResendInvitation`, `RevokeInvitation`, `AcceptInvitation`). Hashed single-use tokens (sha256, 64 random bytes), mail after commit with Arabic/English texts, the invited role vetted by the assignment guardrails through a null-target operation, acceptance as a single locked transaction (identical 410 for every unusable token, 409 for existing users, system-actor role assignment, `invitations.accepted` + `users.activated` audit). The `access:public` marker (`access` middleware) is allowed by the `routes-protected` doctor check for the accept route only; `RoutesRegistrar::registerPublic()` registers it without the `auth*` middleware.

### Fixed
- Invitations: acceptance writes only `name`, `email`, `password` and `email_verified_at` when that column exists, and needs an `AccessUser` model; pending invitations are revoked when the inviter is suspended, deactivated or deleted or loses the authority for the role (`InvitationSweeper`, audit event `invitations.revoked_unauthorized`, severity `warning`); acceptance re-checks the inviter's current authority over the role and locks the super role first; accept refuses invitations of inviters that are gone or cannot sign in (same 410); resend refuses while another pending invitation exists; expiry is exact at `expires_at`; the accept route uses the named limiter `access-invitation-accept` (`roles.users.accept_rate_limit`); `password` is capped at 255.
- Force password reset never falls back to the default broker when the guard's provider has no broker (`passwords.no_broker`) and only mails when the broker resolves the same user id as the target (`passwords.user_mismatch`); `user.in_use` only maps real foreign key violations; a lock ends exactly at `locked_until`.
- `SignInGate` memoizes only a positive users-table check (a table created later is picked up); remember-me recall of an inactive user on a package JSON route is covered by a test and documented as 422 `validation_failed`.
- `POST roles` with a non-empty `permissions` list also requires `access.roles.update`; `access:<arg>` rejects anything but `any`; `access:any` and `GET me` only consider non-deprecated permissions of the configured guard; the access check detects owners by the configured guard; the previous default role lookup is guard-scoped; README rate limiter wording.

- Login enforcement also covers `Login` (two-factor completion, `Auth::login`, remember-me), package routes append `access.active` (`roles.routes.enforce_active`), a missing state table fails open, locked owners count as active owners, `TargetStanding` ignores deprecated permissions and suspended owner actors; users without a state row are always active (`default_status` only seeds backfilled rows).

Upgrading: run `php artisan roles:install` (or republish migrations), `php artisan migrate`, then `php artisan roles:backfill-users`.

## 2.0.0-alpha.5 — 2026-10-09

### Added
- Permission matrix: `GET matrix`, `PUT matrix` and `PUT roles/{role}/permissions`, backed by the `UpdateRolePermissions` and `UpdateMatrix` Actions (all guardrails asserted for every role before the first write, one transaction, one `roles.permissions_updated` audit entry per changed role, permission cache cleared).
- Guardrails: `OperationType::UpdateRolePermissions` and `Operation::$addedPermissions` / `$removedPermissions`. New always-on `OwnerRoleLockedRule` (`guardrail.owner_role_locked`, applies to the system actor too); `SelfChangeRule`, `EscalationRule` and `HighRiskConfirmRule` now cover role-permission edits. Hosts get the new rule automatically (rules merge additively).
- New error codes `permission.not_found` (404), `permission.deprecated` (409) and `permission.conflicting_changes` (422).

### Changed
- `SelfChangeRule` takes an `OwnerCounter` constructor argument (resolved by the container).

## 2.0.0-alpha.4 — 2026-10-09

### Added
- Guardrails: seven configurable rules with stable `guardrail.*` codes. Wired now into `AssignRoleToUser`, `RemoveRoleFromUser` and `SyncUserRoles`: last owner, self role change, escalation, high-risk confirmation. Implemented and tested but wired when the operation exists (Phase 3/4): self destructive, system role, destructive confirmation, last-owner for suspend/deactivate/delete user. See README "Known limitations".
- Actor resolution follows the default guard selected by middleware (e.g. `auth:sanctum`), then `roles.guard`. Unauthenticated guarded writes outside console fail closed with `guardrail.actor_required`; console and `runAs(null)` are the system.
- `guardrails.prevent_self_destructive` toggle (separate from `prevent_self_escalation`); `GuardrailConfigCheck` doctor check; `AccessException` renders itself (JSON envelope or plain status) and is not reported.
- `SyncUserRoles` writes no audit entry and does not touch the pivot when the set is unchanged (order-insensitive); roles of other guards count as removed.
- Type-to-confirm for user deletion is case-insensitive and trims whitespace.
- Guardrail rules and doctor checks merge additively with the package defaults (host configs cannot silently drop built-ins); disable via `guardrails.disabled` / `doctor.disabled`. New `audit-ready` doctor check.
- Audit: `roles.assigned|removed|synced|seeded` and `permissions.synced` entries written after commit through action-logger, with `Info`/`Warning`/`Critical` severity (`RoleChangeSeverity`).
- `ActorContext` is honored by every Action; `roles:seed`, `roles:owner` and `roles:sync` run as the system actor.
- Role-changing Actions lock the super-role row (`FOR UPDATE`) so concurrent owner removals serialize.

### Changed (breaking)
- `AssignRoleToUser::handle`, `SyncUserRoles::handle`, `HasAccess::assignAccessRole` and `HasAccess::syncAccessRoles` gained a trailing `bool $confirmed = false`.
- Assigning the owner role or any role with a high-risk permission now requires `confirmed: true`, including from the console (`roles:owner` passes it).
- Requires `enadstack/laravel-action-logger ^1.1`.
- Deleting a user without an email can never satisfy the type-to-confirm guardrail (always a mismatch).

## 2.0.0-alpha.3 — 2026-10-09

### Fixed
- `roles:install` rejects `--teams=1` and any `--id-type` other than `int`: teams, uuid and ulid keys are unsupported in 2.0-alpha (planned for a later phase). New `ConfigConsistencyCheck` in `roles:doctor` fails when they are configured.
- `roles:owner` fails cleanly when the user model does not implement `AccessUser`.
- Search over jsonb columns (`label`) works on PostgreSQL.
- Extension stubs are idempotent when their columns already exist; republish matches only well-formed timestamped names.
- `PermissionRegistry` memoizes definitions, so a registry file declaring a function no longer fatals on a second read.
- API error envelope also covers the bare prefix URL and other HTTP exceptions (405, 409, 429).

### Tests
- End-to-end install test, a Gate::before null-for-non-owners pin, a real transaction rollback test and a registry-size-independent sync query-count test.

## 2.0.0-alpha.2 — 2026-10-09

### Changed
- Extension migrations are no longer auto-loaded. They ship as stubs and are published with install-time timestamps (`roles-migrations` tag, done by `roles:install`) so they always run after Spatie's permission tables migration.
- `roles:install` publishes Spatie's migration first (if missing), then the extension migrations.
- The stubs throw a clear `RuntimeException` when the Spatie tables are missing.

### Upgrading from alpha.1
- Alpha.1 auto-loaded the extension migrations. The published stubs now skip themselves when their columns already exist (roles: `label`, permissions: `module`), so publishing and running them on an existing alpha.1 database is safe.

## 2.0.0-alpha.1 — 2026-10-09

### Breaking changes
- Requires Laravel ^13 and spatie/laravel-permission ^8 (PHP ^8.3).
- The Vue UI, v1 routes (`/admin/acl`) and v1 services are removed.
- New `config/roles.php` shape; re-publish and migrate your settings.
- v1 continues on the `1.x` branch.

### Added
- File-based permission registry with AR/EN labels, descriptions and risk levels; `roles:sync` (`--dry-run`, `--prune`).
- `Role` and `Permission` models with translatable labels, soft-deprecation and system roles.
- `HasAccess` trait and `AccessUser` contract; owner (super role) gate; role actions (transaction and cache reset; guardrails and audit arrive in Phase 2).
- Commands: `roles:install`, `roles:sync`, `roles:seed`, `roles:owner`, `roles:doctor` (7 checks, throwing checks reported as FAIL).
- Support layer: `ApiResponse` envelope, `ListQuery`/`ListDefinition` with escaped search, base `Action`.
- Architecture and query-count guard tests; sqlite and pgsql test runs.

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

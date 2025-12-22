# Laravel Roles Package v1.3.0 - QA Checklist

## Pre-Flight Checks

- [ ] All unit tests pass (`./vendor/bin/phpunit --testsuite=Unit`)
- [ ] All feature tests pass (`./vendor/bin/phpunit --testsuite=Feature`)
- [ ] No PHP syntax errors (`php -l src/**/*.php`)
- [ ] Composer dependencies are up to date

---

## 1. Fresh Installation Testing

### 1.1 Package Installation
- [ ] Install package via composer: `composer require enadstack/laravel-roles:^1.3`
- [ ] Publish config: `php artisan vendor:publish --tag=roles-config`
- [ ] Run migrations: `php artisan migrate`
- [ ] Verify config file exists at `config/roles.php`
- [ ] Verify Spatie permission tables are created

### 1.2 Initial Configuration
- [ ] Default guard is `web`
- [ ] Default tenancy mode is `single`
- [ ] UI is disabled by default (`ui.enabled = false`)
- [ ] Cache is enabled by default

---

## 2. Core Functionality Testing

### 2.1 Roles CRUD (via API)
- [ ] **List roles**: `GET /admin/acl/roles` returns paginated list
- [ ] **Create role**: `POST /admin/acl/roles` with `{name, guard_name}` works
- [ ] **Show role**: `GET /admin/acl/roles/{id}` returns role details
- [ ] **Update role**: `PUT /admin/acl/roles/{id}` updates role
- [ ] **Delete role**: `DELETE /admin/acl/roles/{id}` soft deletes
- [ ] **Restore role**: `POST /admin/acl/roles/{id}/restore` restores
- [ ] **Force delete**: `DELETE /admin/acl/roles/{id}/force` permanently removes

### 2.2 Permissions CRUD (via API)
- [ ] **List permissions**: `GET /admin/acl/permissions` returns paginated list
- [ ] **Grouped permissions**: `GET /admin/acl/permissions/grouped` returns by group
- [ ] **Create permission**: `POST /admin/acl/permissions` works
- [ ] **Update permission**: `PUT /admin/acl/permissions/{id}` works
- [ ] **Delete permission**: `DELETE /admin/acl/permissions/{id}` works

### 2.3 Permission Matrix
- [ ] **Get matrix**: `GET /admin/acl/matrix` returns roles × permissions grid
- [ ] Matrix contains all roles
- [ ] Matrix contains all permissions
- [ ] Matrix shows correct assignments
- [ ] Matrix respects guard filter: `?guard=api`

### 2.4 Diff Permission Update
- [ ] **Grant permissions**: `POST /admin/acl/roles/{id}/permissions/diff` with `{grant: [...]}` works
- [ ] **Revoke permissions**: `POST /admin/acl/roles/{id}/permissions/diff` with `{revoke: [...]}` works
- [ ] **Mixed grant/revoke**: Both in same request works
- [ ] **Wildcard grant**: `{grant: ['users.*']}` expands correctly
- [ ] **Wildcard revoke**: `{revoke: ['users.*']}` expands correctly
- [ ] **Star wildcard**: `{grant: ['*']}` grants all permissions
- [ ] Operation is idempotent (running twice has same result)

---

## 3. Sync Command Testing

### 3.1 Basic Sync
- [ ] Run `php artisan roles:sync`
- [ ] Permissions from config are created
- [ ] Roles from config are created
- [ ] Permission mappings are applied

### 3.2 Idempotency
- [ ] Run `php artisan roles:sync` twice
- [ ] No duplicate permissions created
- [ ] No duplicate roles created

### 3.3 Dry Run
- [ ] Run `php artisan roles:sync --dry-run`
- [ ] Output shows what would be created
- [ ] Database is NOT modified

### 3.4 Wildcard Expansion
- [ ] `*` wildcard expands to all permissions
- [ ] `users.*` wildcard expands to all users.* permissions
- [ ] Multiple wildcards expand correctly

---

## 4. Tenancy Mode Testing

### 4.1 Single Tenant Mode
- [ ] Set `tenancy.mode = 'single'`
- [ ] Roles and permissions are global
- [ ] No team_id columns required

### 4.2 Team-Scoped Mode
- [ ] Set `tenancy.mode = 'team_scoped'`
- [ ] Enable Spatie teams: `permission.teams = true`
- [ ] Create roles for team 1
- [ ] Switch to team 2
- [ ] Verify team 1 roles are not visible in team 2

### 4.3 Multi-Database Mode
- [ ] Set `tenancy.mode = 'multi_database'`
- [ ] Configure tenant provider
- [ ] Verify tenant isolation

---

## 5. Guard Testing

### 5.1 Web Guard
- [ ] Set `guard = 'web'`
- [ ] Create role with `guard_name = 'web'`
- [ ] Verify guard is set correctly

### 5.2 API Guard
- [ ] Set `guard = 'api'`
- [ ] Create role with `guard_name = 'api'`
- [ ] Verify guard is set correctly

### 5.3 Guard Isolation
- [ ] Create roles for both guards
- [ ] Filter by `?guard=web` shows only web roles
- [ ] Filter by `?guard=api` shows only api roles

---

## 6. Cache Testing

### 6.1 Cache Enabled
- [ ] Set `cache.enabled = true`
- [ ] First request hits database
- [ ] Second request uses cache
- [ ] Verify fewer queries on second request

### 6.2 Cache Invalidation
- [ ] Create role → cache invalidated
- [ ] Update role → cache invalidated
- [ ] Delete role → cache invalidated
- [ ] Sync permissions → cache invalidated
- [ ] Diff update → cache invalidated

### 6.3 Cache Disabled
- [ ] Set `cache.enabled = false`
- [ ] Verify every request hits database

---

## 7. UI Testing (if enabled)

### 7.1 Enable UI
- [ ] Set `ui.enabled = true`
- [ ] Set `ui.driver = 'vue'`
- [ ] Clear config cache: `php artisan config:clear`

### 7.2 Publish Vue Assets
- [ ] Run `php artisan vendor:publish --tag=laravel-roles-vue`
- [ ] Verify pages published to `resources/js/Pages/LaravelRoles/`

### 7.3 UI Routes
- [ ] Visit `/admin/acl/roles` → Inertia response
- [ ] Visit `/admin/acl/roles/create` → Create page
- [ ] Visit `/admin/acl/roles/{id}/edit` → Edit page
- [ ] Visit `/admin/acl/matrix` → Matrix page

### 7.4 UI Disabled
- [ ] Set `ui.enabled = false`
- [ ] API routes still work
- [ ] UI routes return 404 (if not loaded)

---

## 8. /me Endpoint Testing

### 8.1 Enable Expose Me
- [ ] Set `routes.expose_me = true`

### 8.2 Endpoints
- [ ] `GET /admin/acl/me/roles` returns current user's roles
- [ ] `GET /admin/acl/me/permissions` returns current user's permissions
- [ ] `GET /admin/acl/me/acl` returns combined data

### 8.3 Authentication
- [ ] Unauthenticated requests are rejected

---

## 9. Doctor Command Testing

- [ ] Run `php artisan roles:doctor`
- [ ] Verify configuration check passes
- [ ] Verify database check passes
- [ ] Verify orphaned permissions check
- [ ] Verify guard consistency check

---

## 10. Upgrade Testing (v1.2.2 → v1.3.0)

### 10.1 Pre-Upgrade
- [ ] Document existing roles count
- [ ] Document existing permissions count
- [ ] Document existing mappings

### 10.2 Upgrade
- [ ] Update composer: `composer require enadstack/laravel-roles:^1.3`
- [ ] Published new config (merge manually)
- [ ] Run migrations

### 10.3 Post-Upgrade Verification
- [ ] Existing roles preserved
- [ ] Existing permissions preserved
- [ ] Existing mappings preserved
- [ ] All API endpoints work
- [ ] New endpoints work (diff, matrix)

---

## 11. Performance Testing

### 11.1 Query Count
- [ ] Role listing: ≤ 10 queries
- [ ] Matrix endpoint: ≤ 5 queries
- [ ] No N+1 queries detected

### 11.2 Large Dataset
- [ ] Create 50 roles, 200 permissions
- [ ] Matrix loads in < 1 second
- [ ] Pagination works correctly

---

## Final Sign-Off

| Area | Tester | Date | Status |
|------|--------|------|--------|
| Unit Tests | | | ⬜ |
| Feature Tests | | | ⬜ |
| Manual API Testing | | | ⬜ |
| UI Testing | | | ⬜ |
| Upgrade Testing | | | ⬜ |
| Performance Testing | | | ⬜ |

**Package Ready for Release**: ⬜ Yes / ⬜ No

---

## Notes

_Add any testing notes, issues discovered, or special considerations here._

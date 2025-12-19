# Quick Reference Card - Laravel Roles v2.0

## 🎯 AT-A-GLANCE REFERENCE

This is your quick lookup guide for the Laravel Roles v2.0 package implementation.

---

## 📊 API ENDPOINTS QUICK REFERENCE

### Roles API

| Endpoint | Method | Purpose | Auth | Status |
|----------|--------|---------|------|--------|
| `/api/admin/acl/roles` | GET | List roles | ✓ | 200 |
| `/api/admin/acl/roles` | POST | Create role | ✓ | 201 |
| `/api/admin/acl/roles/{id}` | GET | Show role | ✓ | 200 |
| `/api/admin/acl/roles/{id}` | PUT | Update role | ✓ | 200 |
| `/api/admin/acl/roles/{id}` | DELETE | Soft delete | ✓ | 204 |
| `/api/admin/acl/roles/{id}/restore` | POST | Restore | ✓ | 200 |
| `/api/admin/acl/roles/{id}/force` | DELETE | Force delete | ✓ | 204 |
| `/api/admin/acl/roles/{id}/permissions` | GET | Get permissions | ✓ | 200 |
| `/api/admin/acl/roles/{id}/permissions` | POST | Assign permissions | ✓ | 200 |

### Permissions API

| Endpoint | Method | Purpose | Auth | Status |
|----------|--------|---------|------|--------|
| `/api/admin/acl/permissions` | GET | List permissions | ✓ | 200 |
| `/api/admin/acl/permissions` | POST | Create permission | ✓ | 201 |
| `/api/admin/acl/permissions/{id}` | GET | Show permission | ✓ | 200 |
| `/api/admin/acl/permissions/{id}` | PUT | Update permission | ✓ | 200 |
| `/api/admin/acl/permissions/{id}` | DELETE | Soft delete | ✓ | 204 |
| `/api/admin/acl/permissions-matrix` | GET | Get matrix | ✓ | 200 |
| `/api/admin/acl/permissions-matrix/sync` | POST | Sync matrix | ✓ | 200 |

### User-Role API

| Endpoint | Method | Purpose | Auth | Status |
|----------|--------|---------|------|--------|
| `/api/admin/acl/users/{id}/roles` | GET | Get user roles | ✓ | 200 |
| `/api/admin/acl/users/{id}/roles/assign` | POST | Assign roles | ✓ | 200 |
| `/api/admin/acl/users/{id}/roles/sync` | POST | Sync roles | ✓ | 200 |
| `/api/admin/acl/users/{id}/roles/{roleId}` | DELETE | Revoke role | ✓ | 204 |

---

## 🔧 CONFIGURATION MODES

### Tenancy Modes

| Mode | Use Case | Scope Strategy | Example |
|------|----------|----------------|---------|
| `single` | Single-tenant app | None | Blog, CMS |
| `team_scoped` | Multi-tenant, shared DB | Foreign key (team_id) | SaaS with teams |
| `multi_database` | Multi-tenant, separate DBs | Database per tenant | Enterprise SaaS |

### Configuration Example

```php
// Single tenant
'tenancy' => ['mode' => 'single']

// Team scoped
'tenancy' => [
    'mode' => 'team_scoped',
    'team_foreign_key' => 'team_id',
]

// Multi-database
'tenancy' => [
    'mode' => 'multi_database',
    'provider' => 'stancl/tenancy',
]
```

---

## 🌍 LOCALIZATION SETUP

### Supported Locales

| Locale | Language | RTL | Status |
|--------|----------|-----|--------|
| `en` | English | No | ✓ Default |
| `ar` | Arabic | Yes | ✓ Supported |

### Translation Structure

```json
{
  "roles": {
    "title": "Roles",
    "create": "Create Role",
    "name": "Name",
    "label": "Label"
  }
}
```

### Usage in Code

```php
// Backend
__('roles::roles.title')

// Frontend (Vue)
{{ t('roles.title') }}
```

---

## 🎨 FRONTEND COMPONENTS

### shadcn-vue Components Required

| Component | Purpose | Priority |
|-----------|---------|----------|
| Button | Actions | P0 |
| Input | Forms | P0 |
| Table | Data display | P0 |
| Card | Containers | P0 |
| Dialog | Modals | P0 |
| Select | Dropdowns | P0 |
| Checkbox | Multi-select | P0 |
| Badge | Status | P1 |
| Toast | Notifications | P1 |
| Pagination | Navigation | P1 |
| Skeleton | Loading | P1 |

### Installation Command

```bash
npx shadcn-vue@latest add button input table card dialog select checkbox badge toast pagination skeleton
```

---

## 📁 FILE LOCATIONS

### Backend Files

| File | Location | Purpose |
|------|----------|---------|
| Config | `config/roles.php` | Package configuration |
| Service Provider | `src/Providers/RolesServiceProvider.php` | Bootstrap package |
| Tenancy Adapters | `src/Support/TenancyAdapters/` | Multi-tenancy support |
| Services | `src/Services/` | Business logic |
| Repositories | `src/Repositories/` | Data access |
| Controllers | `src/Http/Controllers/` | API endpoints |
| Requests | `src/Http/Requests/` | Validation |
| Resources | `src/Http/Resources/` | JSON responses |
| Policies | `src/Policies/` | Authorization |
| Events | `src/Events/` | Domain events |

### Frontend Files

| File | Location | Purpose |
|------|----------|---------|
| Pages | `resources/js/Pages/` | Inertia pages |
| Components | `resources/js/Components/` | Reusable UI |
| Composables | `resources/js/Composables/` | Logic hooks |
| Types | `resources/js/Types/` | TypeScript definitions |
| Translations | `resources/lang/` | i18n files |

### Test Files

| File | Location | Purpose |
|------|----------|---------|
| Feature Tests | `tests/Feature/` | API tests |
| Unit Tests | `tests/Unit/` | Logic tests |
| TestCase | `tests/TestCase.php` | Base test class |

---

## 🧪 TEST COMMANDS

```bash
# Run all tests
vendor/bin/pest

# Run with coverage
vendor/bin/pest --coverage --min=80

# Run specific test file
vendor/bin/pest tests/Feature/RoleCrudTest.php

# Run specific test
vendor/bin/pest --filter "creates a role"

# Run in parallel
vendor/bin/pest --parallel

# Generate HTML coverage
vendor/bin/pest --coverage-html coverage
```

---

## 🔐 PERMISSION NAMING CONVENTION

### Format
```
{resource}.{action}
```

### Examples
```
roles.list
roles.create
roles.show
roles.update
roles.delete
roles.restore
roles.force-delete

permissions.list
permissions.show

users.list
users.create
users.update
users.delete
```

### Groups
```
roles       → Role management
permissions → Permission management
users       → User management
posts       → Post management
```

---

## 📊 STATUS CODES

| Code | Meaning | When to Use |
|------|---------|-------------|
| 200 | OK | Successful GET/PUT |
| 201 | Created | Successful POST |
| 204 | No Content | Successful DELETE |
| 401 | Unauthorized | Not authenticated |
| 403 | Forbidden | Not authorized |
| 404 | Not Found | Resource doesn't exist |
| 422 | Unprocessable | Validation failed |
| 500 | Server Error | Unexpected error |

---

## 🎯 VALIDATION RULES

### Role Validation

```php
'name' => 'required|string|max:50|regex:/^[a-z0-9-]+$/|unique:roles,name'
'label' => 'nullable|array'
'label.*' => 'string|max:100'
'description' => 'nullable|array'
'guard_name' => 'required|string|in:web,api,sanctum'
```

### Permission Validation

```php
'name' => 'required|string|max:50|regex:/^[a-z0-9.-]+$/|unique:permissions,name'
'group' => 'required|string|max:50|regex:/^[a-z0-9-]+$/'
'label' => 'nullable|array'
```

---

## 🚀 COMMON TASKS

### Create a Role

```bash
curl -X POST /api/admin/acl/roles \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "editor",
    "label": {"en": "Editor"},
    "guard_name": "web"
  }'
```

### Assign Permissions to Role

```bash
curl -X POST /api/admin/acl/roles/1/permissions \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "permission_ids": [1, 2, 3]
  }'
```

### Assign Roles to User

```bash
curl -X POST /api/admin/acl/users/1/roles/assign \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "role_ids": [1, 2]
  }'
```

### Get Permission Matrix

```bash
curl -X GET /api/admin/acl/permissions-matrix \
  -H "Authorization: Bearer TOKEN"
```

---

## 🔄 CACHE INVALIDATION

### When Cache is Cleared

| Action | Cache Cleared |
|--------|---------------|
| Create Role | Spatie + Package |
| Update Role | Spatie + Package |
| Delete Role | Spatie + Package |
| Assign Permissions | Spatie + Package |
| Create Permission | Spatie + Package |
| Update Permission | Spatie + Package |
| Sync Matrix | Spatie + Package |
| Assign User Role | Spatie |

### Manual Cache Clear

```bash
# Clear Spatie cache
php artisan permission:cache-reset

# Clear package cache
php artisan cache:forget laravel_roles.permission_matrix
php artisan cache:forget laravel_roles.grouped_permissions
```

---

## 🎨 UI ROUTES

### Inertia Routes

| Route | Page | Purpose |
|-------|------|---------|
| `/admin/acl/roles` | Roles/Index.vue | List roles |
| `/admin/acl/roles/create` | Roles/Create.vue | Create role |
| `/admin/acl/roles/{id}` | Roles/Show.vue | View role |
| `/admin/acl/roles/{id}/edit` | Roles/Edit.vue | Edit role |
| `/admin/acl/permissions` | Permissions/Index.vue | List permissions |
| `/admin/acl/permissions/create` | Permissions/Create.vue | Create permission |
| `/admin/acl/matrix` | Matrix/Index.vue | Permission matrix |

---

## 🧩 COMPOSABLES USAGE

### useRoles

```typescript
import { useRoles } from '@/Composables/useRoles';

const { loading, roles, createRole, updateRole, deleteRole } = useRoles();

// Create
await createRole({ name: 'editor', label: { en: 'Editor' } });

// Update
await updateRole(1, { label: { en: 'Senior Editor' } });

// Delete
await deleteRole(1);
```

### useTranslation

```typescript
import { useTranslation } from '@/Composables/useTranslation';

const { t, locale, isRTL, getLocalizedValue } = useTranslation();

// Translate
const title = t('roles.title');

// Get localized value
const label = getLocalizedValue(role.label);
```

---

## 📦 PACKAGE COMMANDS

```bash
# Install package
composer require enadstack/laravel-roles

# Run installer (first time only)
php artisan roles:install

# Sync permissions from config
php artisan roles:sync

# Publish config
php artisan vendor:publish --tag=roles-config

# Publish migrations
php artisan vendor:publish --tag=roles-migrations

# Publish translations
php artisan vendor:publish --tag=roles-lang

# Publish Vue components
php artisan vendor:publish --tag=roles-components
```

---

## 🐛 TROUBLESHOOTING

### Common Issues

| Issue | Solution |
|-------|----------|
| Permissions not working | Run `php artisan permission:cache-reset` |
| Tenancy not scoping | Check `setPermissionsTeamId()` is called |
| Translations missing | Publish lang files with `--tag=roles-lang` |
| UI not loading | Run `npm run build` |
| Tests failing | Run `php artisan migrate --env=testing` |

---

## 📈 PERFORMANCE TIPS

1. **Enable caching** - Set `cache.enabled = true`
2. **Use Redis** - For cache tag support
3. **Eager load** - Use `with('permissions', 'users')`
4. **Paginate** - Always paginate large datasets
5. **Index columns** - Add indexes to `name`, `group`

---

## ✅ PRE-RELEASE CHECKLIST

- [ ] All tests passing (56/56)
- [ ] Code coverage ≥ 80%
- [ ] No linting errors
- [ ] Documentation complete
- [ ] CHANGELOG updated
- [ ] Version bumped
- [ ] Git tag created
- [ ] GitHub release created

---

## 📞 QUICK LINKS

- **Spatie Permission:** https://spatie.be/docs/laravel-permission
- **Inertia.js:** https://inertiajs.com
- **shadcn-vue:** https://www.shadcn-vue.com
- **Pest PHP:** https://pestphp.com
- **Laravel Docs:** https://laravel.com/docs

---

**Quick Reference Version:** 1.0  
**Last Updated:** 2025-12-19  
**Print this page for easy reference during development!**

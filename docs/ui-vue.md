# Vue UI Setup Guide

This document covers how to set up and use the Laravel Roles Vue UI.

## Two Installation Options

### Option 1: Self-Contained UI (Recommended for v1.3.4+)

The self-contained UI includes its own UI primitives (Button, Card, Table, etc.) and doesn't require any host app dependencies like shadcn-vue.

**Requirements:**
- Laravel 11+
- Inertia.js with Vue 3
- Tailwind CSS (for styling)

**Installation:**

```bash
# 1. Install the package
composer require enadstack/laravel-roles

# 2. Publish config and run migrations
php artisan vendor:publish --tag=roles-config
php artisan migrate

# 3. Enable UI in config/roles.php
# Set 'ui.enabled' => true

# 4. Publish the self-contained Vue UI
php artisan vendor:publish --tag=roles-vue-standalone

# 5. Sync permissions
php artisan roles:sync

# 6. Start your dev server
npm run dev
```

**What gets published:**
```
resources/js/
├── Pages/
│   └── LaravelRoles/
│       ├── RolesIndex.vue
│       ├── RoleCreate.vue
│       ├── RoleEdit.vue
│       ├── PermissionsIndex.vue
│       └── PermissionMatrix.vue
└── laravel-roles/
    ├── ui/                 # Internal UI primitives
    │   ├── LrButton.vue
    │   ├── LrCard.vue
    │   ├── LrInput.vue
    │   ├── LrTable.vue
    │   └── ... (all primitives)
    ├── layouts/
    │   └── LaravelRolesLayout.vue
    ├── components/
    │   ├── LrPageHeader.vue
    │   ├── LrEmptyState.vue
    │   └── LrStatsCards.vue
    ├── types/
    └── api/
```

**No additional configuration required!** The pages work out of the box.

---

### Option 2: Legacy Integration (for shadcn-vue users)

If you already have shadcn-vue installed and want to use your app's components:

```bash
php artisan vendor:publish --tag=roles-vue
```

This requires:
- shadcn-vue components installed
- Vite alias configuration for `@/laravel-roles`
- Inertia page resolver configuration

See the [Legacy Setup](#legacy-setup) section for details.

---

## Configuration

### Enable the UI

```php
// config/roles.php
'ui' => [
    'enabled' => true,
    'driver' => 'vue',
    'prefix' => 'admin/acl',
    'middleware' => ['web', 'auth'],
],
```

Or via environment:

```env
ROLES_UI_ENABLED=true
```

### Route Configuration

API and UI routes use different middleware by default:

```php
// config/roles.php
'routes' => [
    'prefix' => 'admin/acl',
    'middleware' => ['web', 'auth'], // Session-based (default)
    // 'middleware' => ['api', 'auth:sanctum'], // For API-only mode
],
```

---

## Accessing the UI

After setup, visit:

- **Roles Index**: `/admin/acl/roles`
- **Create Role**: `/admin/acl/roles/create`
- **Edit Role**: `/admin/acl/roles/{id}/edit`
- **Permissions**: `/admin/acl/permissions`
- **Permission Matrix**: `/admin/acl/matrix`

---

## Pages Included

### Roles Index
- List all roles with search and filters
- Stats cards (total, with permissions, without, active)
- Pagination
- Actions: Edit, Delete, Restore

### Role Create
- Form with name, description, guard selection
- Permission assignment

### Role Edit
- Tabbed interface: Details / Permissions
- Toggle permissions individually or by group
- Delete with confirmation

### Permissions Index
- Grouped permission view
- Search and filter
- Stats cards

### Permission Matrix
- Role selector tabs
- Grouped permissions with accordions
- Single-click toggle for each permission
- Group-level toggle (Grant All / Revoke All)
- Optimistic updates with rollback on failure

---

## Using Host App Layout (Optional)

The self-contained UI includes its own layout (`LaravelRolesLayout.vue`). If you want pages to render inside your app's AppLayout instead:

### Method 1: Edit Pages Directly

After publishing, open each page and wrap the content:

```vue
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
// ... existing imports
</script>

<template>
  <AppLayout>
    <!-- Move existing content here -->
  </AppLayout>
</template>
```

### Method 2: Configure Inertia Default Layout

```typescript
// resources/js/app.ts
import AppLayout from '@/Layouts/AppLayout.vue'

createInertiaApp({
    resolve: async (name) => {
        const page = await resolvePageComponent(`./Pages/${name}.vue`, pages)
        
        // Auto-apply layout to Laravel Roles pages
        if (name.startsWith('LaravelRoles/')) {
            page.default.layout = page.default.layout || AppLayout
        }
        
        return page
    },
    // ...
})
```

---

## Sharing API Configuration

Add to your Blade layout before `</head>`:

```html
<script>
  window.laravelRoles = {
    apiPrefix: '{{ config("roles.routes.prefix") }}',
    uiPrefix: '{{ config("roles.ui.prefix") }}',
  };
</script>
```

---

## Verify Routes

```bash
php artisan route:list --name=roles
```

You should see:
```
GET  admin/acl/roles              roles.ui.roles.index
GET  admin/acl/roles/create       roles.ui.roles.create
GET  admin/acl/roles/{id}/edit    roles.ui.roles.edit
GET  admin/acl/permissions        roles.ui.permissions.index
GET  admin/acl/matrix             roles.ui.matrix
```

---

## Run Diagnostics

```bash
php artisan roles:doctor
```

---

## Legacy Setup

For users who want to use their existing shadcn-vue components:

### 1. Publish Legacy Files

```bash
php artisan vendor:publish --tag=roles-vue
```

### 2. Configure Vite Alias

```typescript
// vite.config.ts
resolve: {
    alias: {
        '@': path.resolve(__dirname, './resources/js'),
        '@/laravel-roles': path.resolve(__dirname, './resources/js/laravel-roles'),
    },
},
```

### 3. Update Inertia Page Resolver

```typescript
// resources/js/app.ts
createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob([
            './Pages/**/*.vue',
            './Pages/LaravelRoles/**/*.vue',
        ])
        return resolvePageComponent(`./Pages/${name}.vue`, pages)
    },
    // ...
})
```

### 4. Install Required shadcn-vue Components

```bash
npx shadcn-vue@latest add button input label textarea card badge table \
    dropdown-menu alert-dialog switch checkbox tabs accordion skeleton \
    separator select breadcrumb toast
```

---

## Next Steps

- [API Reference](api.md)
- [Configuration](configuration.md)
- [Troubleshooting](troubleshooting.md)

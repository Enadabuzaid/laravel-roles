# Vue UI

This document covers setting up and customizing the Vue-based admin UI.

## Overview

The package includes an optional Vue 3 admin UI built with:

- Inertia.js for server-driven SPA
- shadcn-vue for UI components
- Lucide icons
- TypeScript

## Requirements

Your Laravel project must have:

- Vue 3 installed
- Inertia.js with Vue adapter
- shadcn-vue components
- Lucide Vue Next icons
- VueUse (for composables)

---

## Step-by-Step Setup Guide

### Step 1: Install the Package

```bash
composer require enadstack/laravel-roles
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=roles-config
```

### Step 3: Enable UI in Config

Edit `config/roles.php`:

```php
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

### Step 4: Run Migrations

```bash
php artisan migrate
```

This creates the required database tables with metadata columns (label, description, group_label).

### Step 5: Seed Roles & Permissions

```bash
php artisan roles:sync
```

Verify the sync worked:

```bash
php artisan roles:doctor
```

### Step 6: Publish Vue UI Components

```bash
php artisan vendor:publish --tag=roles-vue
```

This publishes:
- Pages → `resources/js/Pages/LaravelRoles/`
- API client → `resources/js/laravel-roles/api/`
- Composables → `resources/js/laravel-roles/composables/`
- Types → `resources/js/laravel-roles/types/`
- Locales → `resources/js/laravel-roles/locales/`
- Components → `resources/js/laravel-roles/components/`

### Step 7: Configure Vite Alias (REQUIRED)

Add the `@/laravel-roles` alias to your `vite.config.ts`:

```typescript
// vite.config.ts
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: [
                'resources/js/**',
                // Add this for package pages refresh
                'resources/js/Pages/LaravelRoles/**',
            ],
        }),
        vue(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
            // REQUIRED: Add this alias for Laravel Roles UI
            '@/laravel-roles': path.resolve(__dirname, './resources/js/laravel-roles'),
        },
    },
});
```

### Step 8: Update Inertia Page Resolver

Update your `resources/js/app.ts` to resolve package pages:

```typescript
// resources/js/app.ts
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'

createInertiaApp({
    resolve: (name) => {
        // Resolve pages from both app and vendor directories
        const pages = import.meta.glob([
            './Pages/**/*.vue',
            // Include Laravel Roles pages
            './Pages/LaravelRoles/**/*.vue',
        ])
        return resolvePageComponent(`./Pages/${name}.vue`, pages)
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el)
    },
})
```

### Step 9: Install Required shadcn-vue Components

```bash
npx shadcn-vue@latest add button input label textarea card badge table \
    dropdown-menu alert-dialog switch checkbox tabs accordion skeleton \
    separator select breadcrumb toast
```

### Step 10: Install Required NPM Dependencies

```bash
npm install @vueuse/core lucide-vue-next
```

### Step 11: Configure Default Layout (Optional but Recommended)

To ensure package pages use your app's layout (sidebar, header, etc.):

**Option A: Edit each page to wrap with your layout**

```vue
<!-- resources/js/Pages/LaravelRoles/RolesIndex.vue -->
<template>
  <AppLayout>
    <!-- existing page content -->
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
// ... rest of imports
</script>
```

**Option B: Use Inertia's default layout**

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

### Step 12: Add API Config to Layout

Add to your base layout before `</head>`:

```html
<script>
  window.laravelRoles = {
    apiPrefix: '{{ config("roles.routes.prefix") }}',
    uiPrefix: '{{ config("roles.ui.prefix") }}',
  };
</script>
```

### Step 13: Verify Routes

```bash
php artisan route:list --name=roles.ui
```

You should see:
```
GET  admin/acl/roles              roles.ui.roles.index
GET  admin/acl/roles/create       roles.ui.roles.create
GET  admin/acl/roles/{id}         roles.ui.roles.show
GET  admin/acl/roles/{id}/edit    roles.ui.roles.edit
GET  admin/acl/permissions        roles.ui.permissions.index
GET  admin/acl/matrix             roles.ui.matrix
```

### Step 14: Access UI Pages

Visit your application:
- Roles: `http://your-app.test/admin/acl/roles`
- Permissions: `http://your-app.test/admin/acl/permissions`
- Matrix: `http://your-app.test/admin/acl/matrix`

---

## Published File Structure

After running `php artisan vendor:publish --tag=roles-vue`:

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
    ├── api/
    │   ├── config.ts
    │   ├── index.ts
    │   ├── rolesApi.ts
    │   ├── permissionsApi.ts
    │   └── matrixApi.ts
    ├── composables/
    │   ├── useRolesApi.ts
    │   ├── usePermissionsApi.ts
    │   ├── useMatrixApi.ts
    │   └── useToast.ts
    ├── components/
    │   ├── ui/
    │   │   ├── PageHeader.vue
    │   │   ├── ConfirmDialog.vue
    │   │   ├── SearchInput.vue
    │   │   ├── DataTableSkeleton.vue
    │   │   └── EmptyState.vue
    │   ├── PermissionToggleRow.vue
    │   ├── PermissionGroupAccordion.vue
    │   ├── ViewToggle.vue
    │   ├── FiltersBar.vue
    │   └── ...
    ├── types/
    │   └── index.ts
    ├── locales/
    │   └── en.ts
    └── README.md
```

## Import Path Convention

All package files use the `@/laravel-roles` namespace:

```typescript
// Package components
import PageHeader from '@/laravel-roles/components/ui/PageHeader.vue';
import { useRolesApi } from '@/laravel-roles/composables/useRolesApi';
import type { Role } from '@/laravel-roles/types';

// Your shadcn-vue components (unchanged)
import { Button } from '@/components/ui/button';
```

---

## Available Pages

### Roles Index

URL: `/admin/acl/roles`

Features:
- List all roles with pagination
- Search roles by name
- Filter by status
- View role statistics
- Create, edit, delete roles
- Soft delete and restore

### Role Create

URL: `/admin/acl/roles/create`

Features:
- Form with name, guard, description
- Initial permission selection
- Validation with error display

### Role Edit

URL: `/admin/acl/roles/{id}/edit`

Features:
- Tabbed interface (Details / Permissions)
- View and edit role info
- Link to permission matrix
- Delete with confirmation

### Permission Matrix

URL: `/admin/acl/matrix`

Features:
- Tabbed view by role
- Grouped permissions with accordion
- Single-click permission toggle
- Group-level toggle (all permissions)
- Optimistic updates with rollback

---

## UI Components

### PageHeader

Consistent page header with breadcrumbs:

```vue
<PageHeader
  title="Roles"
  description="Manage user roles"
  :breadcrumbs="[
    { label: 'Home', href: '/' },
    { label: 'Roles' }
  ]"
>
  <template #actions>
    <Button>Create Role</Button>
  </template>
</PageHeader>
```

### ConfirmDialog

Confirmation modal for destructive actions:

```vue
<ConfirmDialog
  v-model:open="showDeleteDialog"
  title="Delete Role"
  description="Are you sure you want to delete this role?"
  confirm-label="Delete"
  :loading="isDeleting"
  destructive
  @confirm="handleDelete"
/>
```

### SearchInput

Debounced search input:

```vue
<SearchInput
  v-model="searchQuery"
  placeholder="Search roles..."
  :debounce="300"
/>
```

---

## API Client

The published API client handles all backend communication:

```typescript
import { rolesApi, permissionsApi, matrixApi } from '@/laravel-roles/api';

// List roles
const roles = await rolesApi.list({ search: 'admin', page: 1 });

// Toggle permission
await matrixApi.togglePermission(roleId, permissionId, 'users.list', true);

// Grant entire group
await matrixApi.grantGroup(roleId, 'users');
```

## Composables

Reactive state management:

```typescript
import { useRolesApi } from '@/laravel-roles/composables/useRolesApi';

const {
  roles,
  isLoading,
  fetchRoles,
  createRole,
  deleteRole,
} = useRolesApi();

// Fetch roles reactively
onMounted(() => fetchRoles());
```

---

## Layout Integration

### Why Pages May Not Show Your Layout

Package pages are published as standalone Vue components. They don't automatically inherit your app's layout unless you configure it.

### Solution: Wrap Pages with Your Layout

1. **Edit each published page** to import and use your AppLayout
2. **Or configure Inertia** to auto-apply layouts (see Step 11 above)

### Content Slot Compatibility

Package pages are designed to work inside a layout slot:
- They don't assume full-screen layout
- Content renders correctly inside sidebar/header layouts
- No hardcoded widths or positions

---

## Disabling UI (API-Only Mode)

For headless/API-only usage:

```php
// config/roles.php
'ui' => [
    'enabled' => false,
],
```

API routes remain active. Only UI routes are disabled.

---

## Next Steps

- [API Reference](api.md)
- [Permission Matrix](permission-matrix.md)
- [Configuration](configuration.md)
- [Troubleshooting](troubleshooting.md)

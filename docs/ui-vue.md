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

## Enabling the UI

### Step 1: Enable in Config

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

### Step 2: Publish Vue Components (REQUIRED)

```bash
# Recommended: Publish everything (pages + components + API + composables + types)
php artisan vendor:publish --tag=laravel-roles-vue-full

# Or publish pages only (requires manual component setup)
php artisan vendor:publish --tag=laravel-roles-vue

# Or publish just reusable components
php artisan vendor:publish --tag=laravel-roles-components
```

### Step 3: Install shadcn-vue Components

The UI requires these shadcn-vue components:

```bash
npx shadcn-vue@latest add button
npx shadcn-vue@latest add input
npx shadcn-vue@latest add label
npx shadcn-vue@latest add textarea
npx shadcn-vue@latest add card
npx shadcn-vue@latest add badge
npx shadcn-vue@latest add table
npx shadcn-vue@latest add dropdown-menu
npx shadcn-vue@latest add alert-dialog
npx shadcn-vue@latest add switch
npx shadcn-vue@latest add checkbox
npx shadcn-vue@latest add tabs
npx shadcn-vue@latest add accordion
npx shadcn-vue@latest add skeleton
npx shadcn-vue@latest add separator
npx shadcn-vue@latest add select
npx shadcn-vue@latest add breadcrumb
npx shadcn-vue@latest add toast
```

### Step 4: Configure API Prefix

Add this to your base layout:

```html
<script>
  window.laravelRoles = {
    apiPrefix: '{{ config("roles.routes.prefix") }}',
    uiPrefix: '{{ config("roles.ui.prefix") }}',
  };
</script>
```

## Published File Structure

After publishing with `--tag=laravel-roles-vue-full`:

```
resources/js/
├── Pages/
│   └── LaravelRoles/
│       ├── RolesIndex.vue
│       ├── RoleCreate.vue
│       ├── RoleEdit.vue
│       └── PermissionMatrix.vue
├── laravel-roles/
│   ├── api/
│   │   ├── config.ts
│   │   ├── rolesApi.ts
│   │   ├── permissionsApi.ts
│   │   └── matrixApi.ts
│   ├── composables/
│   │   ├── useRolesApi.ts
│   │   ├── usePermissionsApi.ts
│   │   ├── useMatrixApi.ts
│   │   └── useToast.ts
│   ├── components/
│   │   ├── ui/
│   │   │   ├── PageHeader.vue
│   │   │   ├── ConfirmDialog.vue
│   │   │   ├── SearchInput.vue
│   │   │   ├── DataTableSkeleton.vue
│   │   │   └── EmptyState.vue
│   │   ├── PermissionToggleRow.vue
│   │   └── PermissionGroupAccordion.vue
│   ├── types/
│   │   └── index.ts
│   └── locales/
│       └── en.ts
```

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

### DataTableSkeleton

Loading skeleton for tables:

```vue
<DataTableSkeleton :columns="5" :rows="10" />
```

### EmptyState

Empty state with optional action:

```vue
<EmptyState
  title="No roles found"
  description="Get started by creating your first role."
  action-label="Create Role"
  action-href="/admin/acl/roles/create"
/>
```

## Customizing the UI

### Override Pages

Edit published files in `resources/js/Pages/LaravelRoles/`.

### Custom Layout

Wrap pages in your own layout:

```vue
<!-- resources/js/Pages/LaravelRoles/RolesIndex.vue -->
<template>
  <AppLayout>
    <!-- page content -->
  </AppLayout>
</template>
```

Or set in config:

```php
'ui' => [
    'layout' => 'AppLayout',
],
```

### Custom Styling

The components use shadcn-vue which is fully customizable via your Tailwind config and component overrides.

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

## Disabling UI (API-Only Mode)

For headless/API-only usage:

```php
// config/roles.php
'ui' => [
    'enabled' => false,
],
```

API routes remain active. Only UI routes are disabled.

## Next Steps

- [API Reference](api.md)
- [Permission Matrix](permission-matrix.md)
- [Configuration](configuration.md)

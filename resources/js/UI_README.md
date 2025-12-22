# Laravel Roles Package - Vue UI

This directory contains the Vue 3 + Inertia.js UI for the Laravel Roles package.

## Prerequisites

Before using the UI, ensure your Laravel project has:

- Vue 3
- Inertia.js with Vue adapter
- shadcn-vue components installed
- Lucide Vue icons

## Installation

### 1. Enable the UI

In your `.env` file:

```env
ROLES_UI_ENABLED=true
```

Or in `config/roles.php`:

```php
'ui' => [
    'enabled' => true,
    'driver' => 'vue',
    'prefix' => 'admin/acl',
    'middleware' => ['web', 'auth'],
],
```

### 2. Publish the Vue Components

```bash
php artisan vendor:publish --tag=laravel-roles-vue
```

This publishes the Vue components to `resources/js/Pages/LaravelRoles/`.

### 3. Configure Vite/Webpack Aliases

Add the package aliases to your `vite.config.js`:

```js
export default defineConfig({
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './resources/js'),
    },
  },
});
```

### 4. Register Vue Pages

The package pages are published to `resources/js/Pages/LaravelRoles/`:

- `RolesIndex.vue` - List all roles
- `RoleCreate.vue` - Create new role
- `RoleEdit.vue` - Edit existing role
- `PermissionMatrix.vue` - Toggle role permissions

### 5. Configure API Prefix

Add this script in your base layout to configure the API prefix:

```html
<script>
  window.laravelRoles = {
    apiPrefix: '{{ config("roles.routes.prefix") }}',
    uiPrefix: '{{ config("roles.ui.prefix") }}',
  };
</script>
```

## Required shadcn-vue Components

Install these shadcn-vue components:

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

## Usage

### Accessing the UI

Once configured, visit:

- `/admin/acl/roles` - Roles listing
- `/admin/acl/roles/create` - Create role
- `/admin/acl/roles/{id}/edit` - Edit role
- `/admin/acl/matrix` - Permission matrix

### API Client

The UI uses a centralized API client that respects your configuration:

```ts
import { rolesApi, permissionsApi, matrixApi } from '@/laravel-roles/api';

// List roles
const roles = await rolesApi.list({ search: 'admin' });

// Toggle permission
await matrixApi.togglePermission(roleId, permissionId, permissionName, true);

// Grant entire group
await matrixApi.grantGroup(roleId, 'users');
```

### Composables

Use the provided composables for reactive state:

```ts
import { useRolesApi } from '@/laravel-roles/composables/useRolesApi';

const {
  roles,
  isLoading,
  fetchRoles,
  createRole,
  updateRole,
  deleteRole,
} = useRolesApi();
```

### Toast Notifications

The package includes a toast composable:

```ts
import { useToast } from '@/laravel-roles/composables/useToast';

const toast = useToast();
toast.success('Role created!', 'The role was created successfully.');
toast.error('Error', 'Failed to delete role.');
```

## Customization

### Override Components

After publishing, you can customize any component in:

```
resources/js/Pages/LaravelRoles/
```

### Change Layout

Update the layout used by the pages in the UI controller or wrap pages in your layout component.

### i18n Support

The UI respects the package's i18n configuration. Labels are resolved from:

1. Backend response (preferred)
2. Locale translations
3. Raw permission name (fallback)

## File Structure

```
resources/js/
├── api/
│   ├── config.ts       # API configuration
│   ├── rolesApi.ts     # Roles API client
│   ├── permissionsApi.ts # Permissions API client
│   ├── matrixApi.ts    # Matrix API client
│   └── index.ts        # API exports
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
│   └── PermissionGroupAccordion.vue
├── pages/
│   ├── RolesIndex.vue
│   ├── RoleCreate.vue
│   ├── RoleEdit.vue
│   └── PermissionMatrix.vue
├── types/
│   └── index.ts
└── locales/
    └── en.ts
```

## Troubleshooting

### Routes not accessible

1. Ensure `ROLES_UI_ENABLED=true` in `.env`
2. Clear config cache: `php artisan config:clear`
3. Clear route cache: `php artisan route:clear`

### Components not found

Ensure you have all required shadcn-vue components installed.

### API errors

Check that the API prefix matches your configuration.

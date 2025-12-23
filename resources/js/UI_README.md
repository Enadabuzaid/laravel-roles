# Laravel Roles Vue UI

This directory contains the Vue 3 admin UI for the Laravel Roles package.

## Installation

### Step 1: Publish Vue Files

Run the following command to publish all Vue files to your project:

```bash
# Recommended: Publish everything (pages, components, API, composables, types)
php artisan vendor:publish --tag=laravel-roles-vue-full

# Or publish only pages (requires manual component setup)
php artisan vendor:publish --tag=laravel-roles-vue
```

### Step 2: Install Required shadcn-vue Components

The UI requires the following shadcn-vue components:

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

### Step 3: Enable UI in Config

```php
// config/roles.php
'ui' => [
    'enabled' => true,
    'driver' => 'vue',
],
```

### Step 4: Add TypeScript Path Alias

Add this to your `tsconfig.json`:

```json
{
  "compilerOptions": {
    "paths": {
      "@/*": ["./resources/js/*"]
    }
  }
}
```

## Published File Structure

After publishing with `--tag=laravel-roles-vue-full`:

```
resources/js/
├── Pages/
│   └── LaravelRoles/
│       ├── RolesIndex.vue       # Roles listing
│       ├── RoleCreate.vue       # Create role form
│       ├── RoleEdit.vue         # Edit role form
│       └── PermissionMatrix.vue # Permission toggle matrix
└── laravel-roles/
    ├── api/
    │   ├── config.ts           # API configuration
    │   ├── rolesApi.ts         # Roles API client
    │   ├── permissionsApi.ts   # Permissions API client
    │   └── matrixApi.ts        # Matrix API client
    ├── composables/
    │   ├── useRolesApi.ts      # Roles reactive state
    │   ├── usePermissionsApi.ts # Permissions reactive state
    │   ├── useMatrixApi.ts     # Matrix reactive state
    │   └── useToast.ts         # Toast notifications
    ├── components/
    │   ├── PageHeader.vue      # Page header with breadcrumbs
    │   ├── ConfirmDialog.vue   # Confirmation dialog
    │   ├── SearchInput.vue     # Debounced search input
    │   ├── DataTableSkeleton.vue # Loading skeleton
    │   ├── EmptyState.vue      # Empty state display
    │   ├── PermissionToggleRow.vue
    │   └── PermissionGroupAccordion.vue
    └── types/
        └── index.ts            # TypeScript interfaces
```

## Import Paths

The published files use these import paths:

- shadcn-vue components: `@/components/ui/*`
- Laravel Roles components: `@/laravel-roles/components/*`
- Laravel Roles API: `@/laravel-roles/api/*`
- Laravel Roles composables: `@/laravel-roles/composables/*`
- Laravel Roles types: `@/laravel-roles/types`

## Customization

### Modify Pages

Edit files in `resources/js/Pages/LaravelRoles/` to customize:

- Add your own layout wrapper
- Change styling
- Add additional features

### Extend API Layer

Edit files in `resources/js/laravel-roles/api/` to:

- Add authentication headers
- Modify error handling
- Add request interceptors

## Requirements

- Vue 3
- Inertia.js with Vue adapter
- shadcn-vue
- Lucide Vue Next icons
- TypeScript (recommended)

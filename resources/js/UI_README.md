# Laravel Roles - Vue UI

This folder contains the Vue 3 admin UI for the Laravel Roles package.

## Quick Setup

### 1. Add Vite Alias (REQUIRED)

Add this alias to your `vite.config.ts`:

```typescript
import path from 'path';

export default defineConfig({
    // ... other config
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
            // Required for Laravel Roles UI
            '@/laravel-roles': path.resolve(__dirname, './resources/js/laravel-roles'),
        },
    },
});
```

### 2. Install Required shadcn-vue Components

```bash
npx shadcn-vue@latest add button input label textarea card badge table \
    dropdown-menu alert-dialog switch checkbox tabs accordion skeleton \
    separator select breadcrumb toast
```

### 3. Install Required Dependencies

```bash
npm install @vueuse/core lucide-vue-next
```

### 4. Add API Config to Layout

Add to your base layout before `</head>`:

```html
<script>
  window.laravelRoles = {
    apiPrefix: '{{ config("roles.routes.prefix") }}',
    uiPrefix: '{{ config("roles.ui.prefix") }}',
  };
</script>
```

## File Structure

```
laravel-roles/
├── api/              # API client for backend communication
├── composables/      # Vue composables (useRolesApi, etc.)
├── components/       # Reusable Vue components
│   └── ui/           # UI components (PageHeader, ConfirmDialog, etc.)
├── types/            # TypeScript type definitions
└── locales/          # Internationalization files
```

## Import Paths

All imports use the `@/laravel-roles` namespace:

```typescript
// Package components
import PageHeader from '@/laravel-roles/components/ui/PageHeader.vue';
import { useRolesApi } from '@/laravel-roles/composables/useRolesApi';
import type { Role } from '@/laravel-roles/types';

// Your shadcn-vue components (unchanged)
import { Button } from '@/components/ui/button';
```

## Pages

The pages are published to `resources/js/Pages/LaravelRoles/`:

- `RolesIndex.vue` - List and manage roles
- `RoleCreate.vue` - Create new role
- `RoleEdit.vue` - Edit existing role
- `PermissionsIndex.vue` - List and manage permissions
- `PermissionMatrix.vue` - Role-permission matrix

## Customization

All files are published and can be freely modified. The package components are designed to be starting points that you can adapt to your project's needs.

## Need Help?

See the full documentation at [docs/ui-vue.md](../../docs/ui-vue.md).

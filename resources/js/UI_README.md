# Laravel Roles - Vue UI

Complete Vue 3 admin UI for the Laravel Roles package.

## Quick Setup

```bash
# Publish the Vue UI
php artisan vendor:publish --tag=roles-vue
```

That's it! The pages work with your existing Inertia setup.

## File Structure

```
Pages/LaravelRoles/
├── RolesManagement/               # Main dashboard
│   ├── Index.vue                  # Dashboard with stats, cards
│   ├── partials/
│   │   ├── QuickActions.vue
│   │   └── RecentRoles.vue
│   └── Roles/                     # Roles CRUD
│       ├── Index.vue              # List/grid with filters
│       ├── Create.vue             # Create role
│       └── Edit.vue               # Edit role (tabbed)
├── PermissionsManagement/         # Permissions dashboard
│   ├── Index.vue                  # Dashboard
│   ├── partials/
│   │   └── RecentPermissions.vue
│   ├── Permissions/
│   │   └── Index.vue              # List/grid view
│   └── PermissionMatrix/
│       └── Index.vue              # Interactive matrix
└── shared/                        # Reusable components
    ├── StatsCard.vue
    ├── ActionCard.vue
    ├── ViewToggle.vue
    ├── ConfirmDialog.vue
    ├── Pagination.vue
    └── Toast.vue
```

## Routes

| Route | Page |
|-------|------|
| `/admin/acl/` | RolesManagement/Index |
| `/admin/acl/roles` | RolesManagement/Roles/Index |
| `/admin/acl/roles/create` | RolesManagement/Roles/Create |
| `/admin/acl/roles/{id}/edit` | RolesManagement/Roles/Edit |
| `/admin/acl/permissions-management` | PermissionsManagement/Index |
| `/admin/acl/permissions` | PermissionsManagement/Permissions/Index |
| `/admin/acl/matrix` | PermissionsManagement/PermissionMatrix/Index |

## AppLayout Integration

Add to your `app.ts` to apply your layout:

```typescript
resolve: async name => {
  const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
  let page = pages[`./Pages/${name}.vue`]
  
  // Apply layout to LaravelRoles pages
  if (name.startsWith('LaravelRoles/')) {
    page.default.layout = AppLayout
  }
  
  return page
}
```

## Features

- ✅ List/Grid view toggle
- ✅ Search, filter by guard, trashed filter
- ✅ Bulk selection and delete
- ✅ Pagination
- ✅ Permission grouping
- ✅ Live permission sync (optimistic updates)
- ✅ Toast notifications
- ✅ Confirmation dialogs
- ✅ Responsive design
- ✅ Dark mode support

## Need Help?

See [docs/ui-vue.md](../../docs/ui-vue.md).

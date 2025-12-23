# Vue UI Setup Guide

Complete Vue UI for Laravel Roles package (v1.3.5+).

## Quick Start

```bash
# 1. Install package
composer require enadstack/laravel-roles

# 2. Configure
php artisan vendor:publish --tag=roles-config
php artisan migrate

# 3. Enable UI in config/roles.php
# 'ui' => ['enabled' => true]

# 4. Publish Vue UI
php artisan vendor:publish --tag=roles-vue

# 5. Sync permissions
php artisan roles:sync

# 6. Run dev server
npm run dev
```

## Folder Structure

```
resources/js/Pages/LaravelRoles/
├── RolesManagement/                 # Roles dashboard
│   ├── Index.vue                    # Dashboard with stats, cards, recent roles
│   ├── partials/
│   │   ├── QuickActions.vue         # Quick action buttons
│   │   └── RecentRoles.vue          # Recent roles list
│   └── Roles/                       # Roles CRUD
│       ├── Index.vue                # List/Grid view with filters, bulk actions
│       ├── Create.vue               # Create role with permission selection
│       └── Edit.vue                 # Edit role (tabs: Details, Permissions)
│
├── PermissionsManagement/           # Permissions dashboard
│   ├── Index.vue                    # Dashboard with stats, cards
│   ├── partials/
│   │   └── RecentPermissions.vue    # Recent permissions grouped
│   ├── Permissions/                 # Permissions views
│   │   └── Index.vue                # List/Grid view with filters
│   └── PermissionMatrix/            # Matrix page
│       └── Index.vue                # Role selector + permission toggles
│
└── shared/                          # Reusable components
    ├── StatsCard.vue                # Stats card with icon
    ├── ActionCard.vue               # Navigation card
    ├── ViewToggle.vue               # List/Grid toggle
    ├── ConfirmDialog.vue            # Confirmation modal
    ├── Pagination.vue               # Pagination controls
    └── Toast.vue                    # Toast notifications
```

## Routes

| Route | Page | Description |
|-------|------|-------------|
| `/admin/acl/` | RolesManagement/Index | Main dashboard |
| `/admin/acl/roles` | RolesManagement/Roles/Index | Roles list |
| `/admin/acl/roles/create` | RolesManagement/Roles/Create | Create role |
| `/admin/acl/roles/{id}/edit` | RolesManagement/Roles/Edit | Edit role |
| `/admin/acl/permissions-management` | PermissionsManagement/Index | Permissions dashboard |
| `/admin/acl/permissions` | PermissionsManagement/Permissions/Index | Permissions list |
| `/admin/acl/matrix` | PermissionsManagement/PermissionMatrix/Index | Permission matrix |

## Features

### Roles Management Dashboard
- **Stats Cards**: Total Roles, Total Permissions, With Permissions, Trashed
- **Quick Actions**: Create Role, Open Matrix buttons
- **Action Cards**: Navigate to Roles, Permissions, Matrix
- **Recent Roles**: List with edit/delete/restore actions

### Roles Index (List/Grid)
- **View Toggle**: Switch between list and grid view
- **Search**: Search by role name
- **Filters**: Guard (web/api), Trashed (active/with/only)
- **Bulk Actions**: Select multiple + bulk delete
- **Pagination**: Full pagination support
- **Actions**: Edit, Delete, Restore (for trashed)

### Role Create
- **Form Fields**: Name, Description, Guard
- **Permission Selection**: Grouped with search
- **Group Toggle**: Select/deselect all in group
- **Validation**: Real-time error display

### Role Edit
- **Tabbed Interface**: Details tab + Permissions tab
- **Live Sync**: Permission changes sync immediately
- **Role Metadata**: Created, Updated, Users count
- **Delete**: Delete role with confirmation

### Permissions Index (List/Grid)
- **View Toggle**: Grouped view or table view
- **Filters**: Search, Group, Guard
- **Group Cards**: Permissions organized by group
- **Role Count**: Shows how many roles have each permission

### Permission Matrix
- **Role Selector**: Click role tabs to switch
- **Group Toggles**: Toggle all permissions in group
- **Individual Toggles**: Toggle single permissions
- **Optimistic Updates**: Instant feedback with rollback on error

## Configuration

```php
// config/roles.php
'ui' => [
    'enabled' => true,
    'driver' => 'vue',
    'prefix' => 'admin/acl',      // URL prefix
    'middleware' => ['web', 'auth'], // Middleware stack
],
```

## AppLayout Integration

Pages work with your host app's AppLayout. Configure in `app.ts`:

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

## API Endpoints (Backend)

The frontend uses these API endpoints:

```
GET    /admin/acl/roles              # List roles
POST   /admin/acl/roles              # Create role
GET    /admin/acl/roles/{id}         # Get role
PUT    /admin/acl/roles/{id}         # Update role
DELETE /admin/acl/roles/{id}         # Delete role
POST   /admin/acl/roles/{id}/restore # Restore role
POST   /admin/acl/roles/bulk-delete  # Bulk delete

GET    /admin/acl/permissions        # List permissions
GET    /admin/acl/roles-stats        # Role statistics
GET    /admin/acl/permissions-stats  # Permission statistics

PUT    /admin/acl/roles/{id}/sync              # Sync permissions
POST   /admin/acl/roles/{id}/permissions       # Grant permission
DELETE /admin/acl/roles/{id}/permissions/{name} # Revoke permission
```

## Toast Notifications

Global toast system available everywhere:

```typescript
(window as any).__lr_toast.success('Operation completed')
(window as any).__lr_toast.error('Operation failed')
(window as any).__lr_toast.info('Information message')
```

## Styling

Uses Tailwind CSS with shadcn-vue CSS variables:
- `--background`, `--foreground`
- `--card`, `--card-foreground`
- `--primary`, `--primary-foreground`
- `--muted`, `--muted-foreground`
- `--destructive`, `--destructive-foreground`

## Troubleshooting

### 401 Unauthorized
Check middleware config and ensure user is logged in.

### Pages Not Found
```bash
php artisan route:clear
php artisan vendor:publish --tag=roles-vue --force
```

### No Permissions
```bash
php artisan roles:sync
```

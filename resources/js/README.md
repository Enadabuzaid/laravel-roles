# Laravel Roles - Vue Frontend

Complete Vue 3 + TypeScript + shadcn-vue UI for managing roles and permissions.

## 📦 Structure

```
resources/js/
├── components/          # Reusable UI components
│   ├── ViewToggle.vue           # Table/Grid view switcher
│   ├── FiltersBar.vue           # Search and filter controls
│   ├── LocaleBadge.vue          # Locale indicator badge
│   ├── RoleStatsCards.vue       # Role statistics cards
│   ├── RoleTable.vue            # Roles table view
│   ├── RoleGrid.vue             # Roles grid view
│   ├── RoleForm.vue             # Role create/edit form
│   ├── PermissionStatsCards.vue # Permission statistics cards
│   ├── PermissionTable.vue      # Permissions table view
│   └── PermissionsMatrixTable.vue # Permission matrix component
│
├── composables/         # Composition API logic
│   ├── useRolesApi.ts           # Roles API interactions
│   └── usePermissionsApi.ts     # Permissions API interactions
│
├── pages/              # Inertia pages
│   ├── RolesIndex.vue           # Roles list page
│   ├── RoleCreate.vue           # Create role page
│   ├── RoleEdit.vue             # Edit role page
│   ├── PermissionsIndex.vue     # Permissions list page
│   └── PermissionMatrix.vue     # Permission matrix page
│
└── types/              # TypeScript definitions
    └── index.ts                 # All type definitions
```

## 🎨 Tech Stack

- **Vue 3** with `<script setup>` and TypeScript
- **Inertia.js** for SPA-like navigation
- **shadcn-vue** components (Table, Card, Button, etc.)
- **Tailwind CSS** for styling
- **Lucide Vue** for icons
- **Composition API** with composables

## 🚀 Features

### Roles Management
- ✅ List roles (table & grid views)
- ✅ View role details
- ✅ Create new role with permissions
- ✅ Edit existing role
- ✅ Delete/restore roles
- ✅ Clone role with permissions
- ✅ Real-time statistics
- ✅ Search and filters
- ✅ Pagination

### Permissions Management
- ✅ List permissions (table view)
- ✅ View permission details
- ✅ Group by category
- ✅ Real-time statistics
- ✅ Search and filters
- ✅ Pagination

### Permission Matrix
- ✅ Visual role × permission matrix
- ✅ Toggle permissions per role
- ✅ Group by permission category
- ✅ Search within matrix
- ✅ Sticky headers
- ✅ Real-time updates

### Shared Features
- ✅ Dark mode support
- ✅ Responsive design
- ✅ Loading states
- ✅ Empty states
- ✅ Type-safe throughout
- ✅ Internationalization ready

## 📝 Components

### ViewToggle
Switch between table and grid views.

```vue
<ViewToggle v-model="viewMode" />
```

### FiltersBar
Search and filter controls for roles/permissions.

```vue
<FiltersBar
  type="roles"
  :model-value="filters"
  @update:model-value="updateFilters"
/>
```

### RoleTable
Display roles in table format with actions.

```vue
<RoleTable
  :roles="roles"
  :loading="isLoading"
  @view="handleView"
  @edit="handleEdit"
  @delete="handleDelete"
  @restore="handleRestore"
  @clone="handleClone"
/>
```

### RoleGrid
Display roles in card grid format.

```vue
<RoleGrid
  :roles="roles"
  :loading="isLoading"
  @view="handleView"
  @edit="handleEdit"
  @delete="handleDelete"
/>
```

### RoleForm
Form for creating/editing roles with permission selection.

```vue
<RoleForm
  :role="existingRole"
  :permission-groups="permissionGroups"
  :loading="isSaving"
  @submit="handleSubmit"
  @cancel="handleCancel"
/>
```

### PermissionsMatrixTable
Interactive matrix for managing role permissions.

```vue
<PermissionsMatrixTable
  :matrix="matrix"
  :loading="isLoading"
  @toggle="handleToggle"
/>
```

## 🔧 Composables

### useRolesApi

```typescript
const {
  roles,              // Ref<Role[]>
  stats,              // Ref<RoleStats | null>
  meta,               // Ref<PaginationMeta>
  filters,            // Ref<RoleFilters>
  isLoading,          // Ref<boolean>
  hasRoles,           // ComputedRef<boolean>
  isEmpty,            // ComputedRef<boolean>
  fetchRoles,         // (page?: number) => Promise<void>
  fetchStats,         // () => Promise<void>
  createRole,         // (data) => Promise<Role>
  updateRole,         // (id, data) => Promise<Role>
  deleteRole,         // (id) => Promise<void>
  restoreRole,        // (id) => Promise<void>
  updateFilters       // (filters) => void
} = useRolesApi()
```

### usePermissionsApi

```typescript
const {
  permissions,        // Ref<Permission[]>
  permissionGroups,   // Ref<PermissionGroup[]>
  stats,              // Ref<PermissionStats | null>
  matrix,             // Ref<PermissionMatrix | null>
  meta,               // Ref<PaginationMeta>
  filters,            // Ref<PermissionFilters>
  isLoading,          // Ref<boolean>
  hasPermissions,     // ComputedRef<boolean>
  isEmpty,            // ComputedRef<boolean>
  groupedPermissions, // ComputedRef<Record<string, Permission[]>>
  fetchPermissions,   // (page?: number) => Promise<void>
  fetchPermissionGroups, // () => Promise<void>
  fetchStats,         // () => Promise<void>
  fetchMatrix,        // () => Promise<void>
  togglePermission,   // (roleId, permissionId, value) => Promise<void>
  createPermission,   // (data) => Promise<Permission>
  updatePermission,   // (id, data) => Promise<Permission>
  deletePermission,   // (id) => Promise<void>
  updateFilters       // (filters) => void
} = usePermissionsApi()
```

## 🌐 Internationalization

All user-facing strings use translation keys:

```typescript
// Roles
t('roles.roles.title')
t('roles.roles.subtitle')
t('roles.create')
t('roles.edit')
t('roles.delete')

// Permissions
t('roles.permissions.title')
t('roles.permissions.subtitle')
t('roles.permissions.create')

// Matrix
t('roles.matrix.title')
t('roles.matrix.subtitle')

// Common
t('common.actions')
t('common.view')
t('common.edit')
t('common.delete')
t('common.restore')
t('common.search')
t('common.filter')
```

## 🎯 Usage Example

### Basic Roles Page

```vue
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRolesApi } from '@/composables/useRolesApi'
import RoleTable from '@/components/RoleTable.vue'

const { roles, isLoading, fetchRoles } = useRolesApi()
const viewMode = ref<'table' | 'grid'>('table')

onMounted(() => {
  fetchRoles()
})
</script>

<template>
  <div class="container mx-auto py-6">
    <h1>{{ $t('roles.roles.title') }}</h1>
    
    <RoleTable
      :roles="roles"
      :loading="isLoading"
      @edit="handleEdit"
    />
  </div>
</template>
```

### Permission Matrix

```vue
<script setup lang="ts">
import { onMounted } from 'vue'
import { usePermissionsApi } from '@/composables/usePermissionsApi'
import PermissionsMatrixTable from '@/components/PermissionsMatrixTable.vue'

const { matrix, isLoading, fetchMatrix, togglePermission } = usePermissionsApi()

onMounted(() => {
  fetchMatrix()
})

const handleToggle = async (payload) => {
  await togglePermission(
    payload.roleId, 
    payload.permissionId, 
    payload.value
  )
}
</script>

<template>
  <div>
    <h1>{{ $t('roles.matrix.title') }}</h1>
    
    <PermissionsMatrixTable
      v-if="matrix"
      :matrix="matrix"
      :loading="isLoading"
      @toggle="handleToggle"
    />
  </div>
</template>
```

## 🎨 Styling

All components use Tailwind utility classes and shadcn-vue design tokens:

- `bg-card` - Card background
- `text-muted-foreground` - Secondary text
- `border` - Border color
- `rounded-md` - Border radius
- Dark mode automatically supported

## 📡 API Endpoints

The composables expect these endpoints:

### Roles
- `GET /api/roles` - List roles
- `GET /api/roles/{id}` - Show role
- `POST /api/roles` - Create role
- `PUT /api/roles/{id}` - Update role
- `DELETE /api/roles/{id}` - Delete role
- `POST /api/roles/{id}/restore` - Restore role
- `GET /api/roles-stats` - Get statistics

### Permissions
- `GET /api/permissions` - List permissions
- `GET /api/permissions/{id}` - Show permission
- `POST /api/permissions` - Create permission
- `PUT /api/permissions/{id}` - Update permission
- `DELETE /api/permissions/{id}` - Delete permission
- `GET /api/permission-groups` - Get grouped permissions
- `GET /api/permissions-stats` - Get statistics
- `GET /api/permissions-matrix` - Get permission matrix
- `POST /api/roles/matrix` - Toggle permission

## ✅ Type Safety

All components are fully typed:

```typescript
interface Role {
  id: number
  name: string
  display_name?: string
  description?: string
  guard_name: string
  permissions_count?: number
  users_count?: number
  permissions?: Permission[]
}

interface Permission {
  id: number
  name: string
  display_name?: string
  description?: string
  guard_name: string
  group?: string
  roles_count?: number
}

interface PermissionMatrix {
  roles: Role[]
  permissions: Permission[]
  matrix: Record<number, number[]>
}
```

## 🚧 TODOs

- [ ] Add toast notifications for success/error messages
- [ ] Add confirmation dialogs
- [ ] Add permission grid view component
- [ ] Add role show/detail page
- [ ] Add permission show/detail page
- [ ] Add export functionality
- [ ] Add bulk operations
- [ ] Add keyboard shortcuts
- [ ] Add loading skeletons
- [ ] Add error boundaries

## 📄 License

MIT


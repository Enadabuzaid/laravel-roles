# Frontend Implementation Guide - Laravel Roles v2.0

## 🎨 FRONTEND ARCHITECTURE (Inertia + Vue 3 + shadcn-vue)

### 1. PROJECT STRUCTURE

```
resources/
├── js/
│   ├── Pages/
│   │   ├── Roles/
│   │   │   ├── Index.vue
│   │   │   ├── Create.vue
│   │   │   ├── Edit.vue
│   │   │   └── Show.vue
│   │   ├── Permissions/
│   │   │   ├── Index.vue
│   │   │   ├── Create.vue
│   │   │   ├── Edit.vue
│   │   │   └── Show.vue
│   │   └── Matrix/
│   │       └── Index.vue
│   │
│   ├── Components/
│   │   ├── Roles/
│   │   │   ├── PageHeader.vue
│   │   │   ├── DataTable.vue
│   │   │   ├── RoleCard.vue
│   │   │   ├── RoleForm.vue
│   │   │   ├── Filters.vue
│   │   │   └── PermissionSelector.vue
│   │   ├── Permissions/
│   │   │   ├── PermissionTable.vue
│   │   │   ├── PermissionForm.vue
│   │   │   └── GroupSelector.vue
│   │   ├── Matrix/
│   │   │   ├── MatrixGrid.vue
│   │   │   └── MatrixCell.vue
│   │   └── ui/  (shadcn-vue components)
│   │
│   ├── Composables/
│   │   ├── useRoles.ts
│   │   ├── usePermissions.ts
│   │   ├── useMatrix.ts
│   │   └── useTranslation.ts
│   │
│   ├── Types/
│   │   ├── roles.d.ts
│   │   ├── permissions.d.ts
│   │   └── api.d.ts
│   │
│   └── Layouts/
│       └── AdminLayout.vue
│
└── lang/
    ├── en/
    │   ├── roles.json
    │   └── permissions.json
    └── ar/
        ├── roles.json
        └── permissions.json
```

---

### 2. TYPE DEFINITIONS

**File:** `resources/js/Types/roles.d.ts`

```typescript
export interface Role {
  id: number;
  name: string;
  guard_name: string;
  label: Record<string, string> | null;
  description: Record<string, string> | null;
  permissions_count?: number;
  users_count?: number;
  created_at: string;
  updated_at: string;
  deleted_at?: string | null;
}

export interface Permission {
  id: number;
  name: string;
  group: string;
  guard_name: string;
  label: Record<string, string> | null;
  description: Record<string, string> | null;
  created_at: string;
  updated_at: string;
}

export interface PermissionMatrix {
  roles: Role[];
  matrix: MatrixRow[];
  generated_at: string;
}

export interface MatrixRow {
  permission_id: number;
  permission_name: string;
  permission_label: string;
  group: string;
  roles: Record<number, boolean>;
}

export interface PaginatedResponse<T> {
  data: T[];
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
  };
}

export interface FilterState {
  search?: string;
  sort_by?: string;
  sort_order?: 'asc' | 'desc';
  per_page?: number;
  with_trashed?: boolean;
  only_trashed?: boolean;
  group?: string;
}
```

**File:** `resources/js/Types/api.d.ts`

```typescript
export interface ApiResponse<T = any> {
  data: T;
  message?: string;
  meta?: {
    timestamp: string;
    locale: string;
  };
}

export interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
}
```

---

### 3. COMPOSABLES

**File:** `resources/js/Composables/useRoles.ts`

```typescript
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from '@/Components/ui/toast';
import type { Role, PaginatedResponse, FilterState } from '@/Types/roles';

export function useRoles() {
  const { toast } = useToast();
  const loading = ref(false);
  const roles = ref<PaginatedResponse<Role> | null>(null);
  
  const fetchRoles = async (filters: FilterState = {}) => {
    loading.value = true;
    
    router.get(
      route('roles.index'),
      filters,
      {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          roles.value = page.props.roles as PaginatedResponse<Role>;
        },
        onFinish: () => {
          loading.value = false;
        },
      }
    );
  };
  
  const createRole = async (data: Partial<Role>) => {
    loading.value = true;
    
    router.post(
      route('roles.store'),
      data,
      {
        onSuccess: () => {
          toast({
            title: 'Success',
            description: 'Role created successfully',
          });
        },
        onError: (errors) => {
          toast({
            title: 'Error',
            description: Object.values(errors).flat().join(', '),
            variant: 'destructive',
          });
        },
        onFinish: () => {
          loading.value = false;
        },
      }
    );
  };
  
  const updateRole = async (id: number, data: Partial<Role>) => {
    loading.value = true;
    
    router.put(
      route('roles.update', id),
      data,
      {
        onSuccess: () => {
          toast({
            title: 'Success',
            description: 'Role updated successfully',
          });
        },
        onError: (errors) => {
          toast({
            title: 'Error',
            description: Object.values(errors).flat().join(', '),
            variant: 'destructive',
          });
        },
        onFinish: () => {
          loading.value = false;
        },
      }
    );
  };
  
  const deleteRole = async (id: number) => {
    if (!confirm('Are you sure you want to delete this role?')) {
      return;
    }
    
    loading.value = true;
    
    router.delete(
      route('roles.destroy', id),
      {
        onSuccess: () => {
          toast({
            title: 'Success',
            description: 'Role deleted successfully',
          });
        },
        onFinish: () => {
          loading.value = false;
        },
      }
    );
  };
  
  const restoreRole = async (id: number) => {
    loading.value = true;
    
    router.post(
      route('roles.restore', id),
      {},
      {
        onSuccess: () => {
          toast({
            title: 'Success',
            description: 'Role restored successfully',
          });
        },
        onFinish: () => {
          loading.value = false;
        },
      }
    );
  };
  
  return {
    loading,
    roles,
    fetchRoles,
    createRole,
    updateRole,
    deleteRole,
    restoreRole,
  };
}
```

**File:** `resources/js/Composables/useTranslation.ts`

```typescript
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useTranslation() {
  const page = usePage();
  const locale = computed(() => page.props.locale || 'en');
  const isRTL = computed(() => ['ar'].includes(locale.value));
  
  const t = (key: string, replacements: Record<string, string> = {}): string => {
    const translations = page.props.translations || {};
    let translation = key.split('.').reduce((obj, k) => obj?.[k], translations) as string;
    
    if (!translation) {
      return key;
    }
    
    // Replace placeholders
    Object.entries(replacements).forEach(([key, value]) => {
      translation = translation.replace(`:${key}`, value);
    });
    
    return translation;
  };
  
  const getLocalizedValue = (value: Record<string, string> | string | null): string => {
    if (!value) return '';
    if (typeof value === 'string') return value;
    
    return value[locale.value] || value['en'] || Object.values(value)[0] || '';
  };
  
  return {
    t,
    locale,
    isRTL,
    getLocalizedValue,
  };
}
```

---

### 4. PAGES

**File:** `resources/js/Pages/Roles/Index.vue`

```vue
<script setup lang="ts">
import { ref, reactive, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Roles/PageHeader.vue';
import DataTable from '@/Components/Roles/DataTable.vue';
import Filters from '@/Components/Roles/Filters.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { 
  Select, 
  SelectContent, 
  SelectItem, 
  SelectTrigger, 
  SelectValue 
} from '@/Components/ui/select';
import { useRoles } from '@/Composables/useRoles';
import { useTranslation } from '@/Composables/useTranslation';
import type { Role, PaginatedResponse, FilterState } from '@/Types/roles';

const props = defineProps<{
  roles: PaginatedResponse<Role>;
  filters: FilterState;
}>();

const { t, isRTL } = useTranslation();
const { deleteRole, restoreRole } = useRoles();

const viewMode = ref<'grid' | 'list'>('list');

const filters = reactive<FilterState>({
  search: props.filters.search || '',
  sort_by: props.filters.sort_by || 'created_at',
  sort_order: props.filters.sort_order || 'desc',
  per_page: props.filters.per_page || 15,
  with_trashed: props.filters.with_trashed || false,
});

const handleFilter = () => {
  router.get(route('roles.index'), filters, {
    preserveState: true,
    preserveScroll: true,
  });
};

const handleDelete = (id: number) => {
  deleteRole(id);
};

const handleRestore = (id: number) => {
  restoreRole(id);
};
</script>

<template>
  <AdminLayout>
    <Head :title="t('roles.title')" />
    
    <div class="space-y-6" :dir="isRTL ? 'rtl' : 'ltr'">
      <!-- Page Header -->
      <PageHeader
        :title="t('roles.title')"
        :description="t('roles.description')"
        :create-route="route('roles.create')"
        :create-label="t('roles.create')"
      />
      
      <!-- Filters -->
      <Filters
        v-model:search="filters.search"
        v-model:sort-by="filters.sort_by"
        v-model:sort-order="filters.sort_order"
        v-model:with-trashed="filters.with_trashed"
        @update="handleFilter"
      />
      
      <!-- View Mode Toggle -->
      <div class="flex justify-end gap-2">
        <Button
          variant="outline"
          size="icon"
          @click="viewMode = 'list'"
          :class="{ 'bg-accent': viewMode === 'list' }"
        >
          <ListIcon class="h-4 w-4" />
        </Button>
        <Button
          variant="outline"
          size="icon"
          @click="viewMode = 'grid'"
          :class="{ 'bg-accent': viewMode === 'grid' }"
        >
          <GridIcon class="h-4 w-4" />
        </Button>
      </div>
      
      <!-- Data Table -->
      <DataTable
        :data="roles.data"
        :view-mode="viewMode"
        @delete="handleDelete"
        @restore="handleRestore"
      />
      
      <!-- Pagination -->
      <div class="flex items-center justify-between">
        <p class="text-sm text-muted-foreground">
          {{ t('common.showing') }} {{ roles.meta.from }} {{ t('common.to') }} {{ roles.meta.to }}
          {{ t('common.of') }} {{ roles.meta.total }} {{ t('common.results') }}
        </p>
        
        <div class="flex gap-2">
          <Button
            v-if="roles.links.prev"
            variant="outline"
            :href="roles.links.prev"
          >
            {{ t('common.previous') }}
          </Button>
          <Button
            v-if="roles.links.next"
            variant="outline"
            :href="roles.links.next"
          >
            {{ t('common.next') }}
          </Button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
```

**File:** `resources/js/Pages/Roles/Create.vue`

```vue
<script setup lang="ts">
import { reactive } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RoleForm from '@/Components/Roles/RoleForm.vue';
import { useRoles } from '@/Composables/useRoles';
import { useTranslation } from '@/Composables/useTranslation';
import type { Permission } from '@/Types/roles';

const props = defineProps<{
  permissions: Permission[];
}>();

const { t } = useTranslation();
const { createRole, loading } = useRoles();

const form = reactive({
  name: '',
  label: { en: '', ar: '' },
  description: { en: '', ar: '' },
  permission_ids: [] as number[],
});

const handleSubmit = () => {
  createRole(form);
};
</script>

<template>
  <AdminLayout>
    <Head :title="t('roles.create')" />
    
    <div class="max-w-4xl mx-auto space-y-6">
      <div>
        <h1 class="text-3xl font-bold">{{ t('roles.create') }}</h1>
        <p class="text-muted-foreground">{{ t('roles.create_description') }}</p>
      </div>
      
      <RoleForm
        v-model="form"
        :permissions="permissions"
        :loading="loading"
        @submit="handleSubmit"
      />
    </div>
  </AdminLayout>
</template>
```

**File:** `resources/js/Pages/Matrix/Index.vue`

```vue
<script setup lang="ts">
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MatrixGrid from '@/Components/Matrix/MatrixGrid.vue';
import { Button } from '@/Components/ui/button';
import { useToast } from '@/Components/ui/toast';
import { useTranslation } from '@/Composables/useTranslation';
import type { PermissionMatrix } from '@/Types/roles';

const props = defineProps<{
  matrix: PermissionMatrix;
}>();

const { t } = useTranslation();
const { toast } = useToast();

const matrixState = reactive<Record<number, Record<number, boolean>>>({});
const loading = ref(false);

// Initialize matrix state
props.matrix.matrix.forEach((row) => {
  matrixState[row.permission_id] = { ...row.roles };
});

const handleCellToggle = (permissionId: number, roleId: number) => {
  if (!matrixState[permissionId]) {
    matrixState[permissionId] = {};
  }
  matrixState[permissionId][roleId] = !matrixState[permissionId][roleId];
};

const handleSave = () => {
  loading.value = true;
  
  // Transform matrix state to API format
  const updates = Object.entries(matrixState).map(([permissionId, roles]) => ({
    permission_id: Number(permissionId),
    role_ids: Object.entries(roles)
      .filter(([_, enabled]) => enabled)
      .map(([roleId]) => Number(roleId)),
  }));
  
  router.post(
    route('matrix.sync'),
    { matrix: updates },
    {
      onSuccess: () => {
        toast({
          title: t('common.success'),
          description: t('matrix.success_saved'),
        });
      },
      onError: (errors) => {
        toast({
          title: t('common.error'),
          description: Object.values(errors).flat().join(', '),
          variant: 'destructive',
        });
      },
      onFinish: () => {
        loading.value = false;
      },
    }
  );
};
</script>

<template>
  <AdminLayout>
    <Head :title="t('matrix.title')" />
    
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold">{{ t('matrix.title') }}</h1>
          <p class="text-muted-foreground">{{ t('matrix.description') }}</p>
        </div>
        
        <Button @click="handleSave" :disabled="loading">
          {{ loading ? t('common.saving') : t('matrix.save') }}
        </Button>
      </div>
      
      <MatrixGrid
        :matrix="matrix"
        :state="matrixState"
        @toggle="handleCellToggle"
      />
    </div>
  </AdminLayout>
</template>
```

---

### 5. COMPONENTS

**File:** `resources/js/Components/Roles/DataTable.vue`

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { useTranslation } from '@/Composables/useTranslation';
import type { Role } from '@/Types/roles';

const props = defineProps<{
  data: Role[];
  viewMode: 'grid' | 'list';
}>();

const emit = defineEmits<{
  delete: [id: number];
  restore: [id: number];
}>();

const { t, getLocalizedValue } = useTranslation();
</script>

<template>
  <!-- List View -->
  <div v-if="viewMode === 'list'" class="rounded-md border">
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{{ t('roles.name') }}</TableHead>
          <TableHead>{{ t('roles.label') }}</TableHead>
          <TableHead>{{ t('roles.permissions') }}</TableHead>
          <TableHead>{{ t('roles.users_count') }}</TableHead>
          <TableHead>{{ t('roles.created_at') }}</TableHead>
          <TableHead class="text-right">{{ t('roles.actions') }}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <TableRow v-for="role in data" :key="role.id">
          <TableCell class="font-medium">
            {{ role.name }}
            <Badge v-if="role.deleted_at" variant="destructive" class="ml-2">
              {{ t('common.deleted') }}
            </Badge>
          </TableCell>
          <TableCell>{{ getLocalizedValue(role.label) }}</TableCell>
          <TableCell>{{ role.permissions_count || 0 }}</TableCell>
          <TableCell>{{ role.users_count || 0 }}</TableCell>
          <TableCell>{{ new Date(role.created_at).toLocaleDateString() }}</TableCell>
          <TableCell class="text-right">
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="ghost" size="icon">
                  <MoreVerticalIcon class="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem as-child>
                  <Link :href="route('roles.show', role.id)">
                    {{ t('common.view') }}
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem v-if="!role.deleted_at" as-child>
                  <Link :href="route('roles.edit', role.id)">
                    {{ t('common.edit') }}
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem
                  v-if="!role.deleted_at"
                  @click="emit('delete', role.id)"
                  class="text-destructive"
                >
                  {{ t('common.delete') }}
                </DropdownMenuItem>
                <DropdownMenuItem
                  v-if="role.deleted_at"
                  @click="emit('restore', role.id)"
                >
                  {{ t('common.restore') }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </TableCell>
        </TableRow>
      </TableBody>
    </Table>
  </div>
  
  <!-- Grid View -->
  <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <RoleCard
      v-for="role in data"
      :key="role.id"
      :role="role"
      @delete="emit('delete', role.id)"
      @restore="emit('restore', role.id)"
    />
  </div>
</template>
```

**File:** `resources/js/Components/Matrix/MatrixGrid.vue`

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Checkbox } from '@/Components/ui/checkbox';
import { ScrollArea } from '@/Components/ui/scroll-area';
import { useTranslation } from '@/Composables/useTranslation';
import type { PermissionMatrix } from '@/Types/roles';

const props = defineProps<{
  matrix: PermissionMatrix;
  state: Record<number, Record<number, boolean>>;
}>();

const emit = defineEmits<{
  toggle: [permissionId: number, roleId: number];
}>();

const { getLocalizedValue } = useTranslation();

// Group permissions by group
const groupedMatrix = computed(() => {
  const groups: Record<string, typeof props.matrix.matrix> = {};
  
  props.matrix.matrix.forEach((row) => {
    if (!groups[row.group]) {
      groups[row.group] = [];
    }
    groups[row.group].push(row);
  });
  
  return groups;
});
</script>

<template>
  <ScrollArea class="h-[600px] rounded-md border">
    <div class="p-4">
      <div v-for="(rows, group) in groupedMatrix" :key="group" class="mb-8">
        <h3 class="text-lg font-semibold mb-4 sticky top-0 bg-background z-10 py-2">
          {{ group }}
        </h3>
        
        <div class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead>
              <tr>
                <th class="text-left p-2 border-b font-medium min-w-[200px]">
                  Permission
                </th>
                <th
                  v-for="role in matrix.roles"
                  :key="role.id"
                  class="text-center p-2 border-b font-medium min-w-[100px]"
                >
                  {{ getLocalizedValue(role.label) || role.name }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.permission_id" class="hover:bg-muted/50">
                <td class="p-2 border-b">
                  <div>
                    <div class="font-medium">{{ row.permission_label }}</div>
                    <div class="text-sm text-muted-foreground">{{ row.permission_name }}</div>
                  </div>
                </td>
                <td
                  v-for="role in matrix.roles"
                  :key="role.id"
                  class="p-2 border-b text-center"
                >
                  <Checkbox
                    :checked="state[row.permission_id]?.[role.id] || false"
                    @update:checked="emit('toggle', row.permission_id, role.id)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </ScrollArea>
</template>
```

---

### 6. TRANSLATIONS

**File:** `resources/lang/en/roles.json`

```json
{
  "roles": {
    "title": "Roles",
    "description": "Manage user roles and permissions",
    "create": "Create Role",
    "create_description": "Create a new role with specific permissions",
    "edit": "Edit Role",
    "delete": "Delete Role",
    "restore": "Restore Role",
    "confirm_delete": "Are you sure you want to delete this role?",
    "name": "Name",
    "label": "Label",
    "description": "Description",
    "permissions": "Permissions",
    "users_count": "Users",
    "created_at": "Created At",
    "actions": "Actions",
    "search_placeholder": "Search roles...",
    "no_results": "No roles found",
    "success_created": "Role created successfully",
    "success_updated": "Role updated successfully",
    "success_deleted": "Role deleted successfully",
    "success_restored": "Role restored successfully"
  },
  "permissions": {
    "title": "Permissions",
    "description": "Manage system permissions",
    "create": "Create Permission",
    "name": "Name",
    "group": "Group",
    "label": "Label",
    "description": "Description"
  },
  "matrix": {
    "title": "Permission Matrix",
    "description": "Manage role-permission assignments in a visual grid",
    "save": "Save Changes",
    "success_saved": "Permissions updated successfully"
  },
  "common": {
    "view": "View",
    "edit": "Edit",
    "delete": "Delete",
    "restore": "Restore",
    "save": "Save",
    "cancel": "Cancel",
    "success": "Success",
    "error": "Error",
    "loading": "Loading...",
    "saving": "Saving...",
    "deleted": "Deleted",
    "showing": "Showing",
    "to": "to",
    "of": "of",
    "results": "results",
    "previous": "Previous",
    "next": "Next"
  }
}
```

**File:** `resources/lang/ar/roles.json`

```json
{
  "roles": {
    "title": "الأدوار",
    "description": "إدارة أدوار المستخدمين والصلاحيات",
    "create": "إنشاء دور",
    "create_description": "إنشاء دور جديد بصلاحيات محددة",
    "edit": "تعديل الدور",
    "delete": "حذف الدور",
    "restore": "استعادة الدور",
    "confirm_delete": "هل أنت متأكد من حذف هذا الدور؟",
    "name": "الاسم",
    "label": "التسمية",
    "description": "الوصف",
    "permissions": "الصلاحيات",
    "users_count": "المستخدمين",
    "created_at": "تاريخ الإنشاء",
    "actions": "الإجراءات",
    "search_placeholder": "البحث عن الأدوار...",
    "no_results": "لا توجد أدوار",
    "success_created": "تم إنشاء الدور بنجاح",
    "success_updated": "تم تحديث الدور بنجاح",
    "success_deleted": "تم حذف الدور بنجاح",
    "success_restored": "تم استعادة الدور بنجاح"
  },
  "matrix": {
    "title": "مصفوفة الصلاحيات",
    "description": "إدارة تعيينات الأدوار والصلاحيات في شبكة مرئية",
    "save": "حفظ التغييرات",
    "success_saved": "تم تحديث الصلاحيات بنجاح"
  },
  "common": {
    "view": "عرض",
    "edit": "تعديل",
    "delete": "حذف",
    "restore": "استعادة",
    "save": "حفظ",
    "cancel": "إلغاء",
    "success": "نجاح",
    "error": "خطأ",
    "loading": "جاري التحميل...",
    "saving": "جاري الحفظ...",
    "deleted": "محذوف",
    "showing": "عرض",
    "to": "إلى",
    "of": "من",
    "results": "نتيجة",
    "previous": "السابق",
    "next": "التالي"
  }
}
```

---

### 7. STYLING & THEMING

**File:** `resources/css/roles.css`

```css
/* RTL Support */
[dir="rtl"] {
  direction: rtl;
  text-align: right;
}

[dir="rtl"] .text-left {
  text-align: right;
}

[dir="rtl"] .text-right {
  text-align: left;
}

/* Matrix Grid Enhancements */
.matrix-grid {
  @apply relative;
}

.matrix-grid thead th {
  @apply sticky top-0 bg-background z-20;
}

.matrix-grid tbody tr:hover {
  @apply bg-muted/50 transition-colors;
}

.matrix-cell {
  @apply transition-all duration-200;
}

.matrix-cell:hover {
  @apply bg-accent/50 scale-110;
}

/* Role Card Hover Effects */
.role-card {
  @apply transition-all duration-300;
}

.role-card:hover {
  @apply shadow-lg scale-105;
}

/* Premium Design Enhancements */
.premium-gradient {
  background: linear-gradient(135deg, hsl(var(--primary)) 0%, hsl(var(--accent)) 100%);
}

.glass-morphism {
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

/* Animations */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in {
  animation: fadeIn 0.3s ease-out;
}

/* Loading States */
.skeleton {
  @apply animate-pulse bg-muted rounded;
}
```

---

### 8. INSTALLATION STEPS

```bash
# 1. Install shadcn-vue
npx shadcn-vue@latest init

# 2. Add required components
npx shadcn-vue@latest add button input label table card badge dialog select checkbox switch pagination skeleton toast dropdown-menu tabs alert separator scroll-area

# 3. Install additional dependencies
npm install @vueuse/core

# 4. Configure Inertia (if not already done)
npm install @inertiajs/vue3

# 5. Build assets
npm run build
```

---

**Frontend Guide Version:** 1.0  
**Last Updated:** 2025-12-19

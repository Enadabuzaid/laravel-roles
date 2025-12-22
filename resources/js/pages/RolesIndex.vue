<script setup lang="ts">
/**
 * RolesIndex Page
 *
 * Roles listing page with CRUD operations.
 * Uses Inertia.js and shadcn-vue components.
 */
import { ref, onMounted, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Skeleton } from '@/components/ui/skeleton'
import {
  PlusIcon,
  MoreHorizontalIcon,
  PencilIcon,
  Trash2Icon,
  RotateCcwIcon,
  EyeIcon,
  CopyIcon,
  ShieldIcon,
  UsersIcon,
  AlertTriangleIcon,
} from 'lucide-vue-next'

import PageHeader from '@/components/ui/PageHeader.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import DataTableSkeleton from '@/components/ui/DataTableSkeleton.vue'

import { useRolesApi } from '@/composables/useRolesApi'
import { uiUrl } from '@/api/config'
import type { Role } from '@/types'

// Props from Inertia
const props = defineProps<{
  config?: {
    prefix: string
    guard: string
  }
}>()

// Composable
const {
  roles,
  stats,
  meta,
  filters,
  isLoading,
  isSaving,
  fetchRoles,
  fetchStats,
  deleteRole,
  restoreRole,
  forceDeleteRole,
  updateFilters,
} = useRolesApi()

// Local state
const deleteDialog = ref({
  open: false,
  role: null as Role | null,
  force: false,
})

// Lifecycle
onMounted(async () => {
  await Promise.all([fetchRoles(), fetchStats()])
})

// Navigation
const navigateToCreate = () => {
  router.visit(uiUrl('/roles/create'))
}

const navigateToEdit = (role: Role) => {
  router.visit(uiUrl(`/roles/${role.id}/edit`))
}

const navigateToShow = (role: Role) => {
  router.visit(uiUrl(`/roles/${role.id}`))
}

// Delete handlers
const openDeleteDialog = (role: Role, force = false) => {
  deleteDialog.value = { open: true, role, force }
}

const handleDelete = async () => {
  if (!deleteDialog.value.role) return

  if (deleteDialog.value.force) {
    await forceDeleteRole(deleteDialog.value.role.id)
  } else {
    await deleteRole(deleteDialog.value.role.id)
  }

  deleteDialog.value = { open: false, role: null, force: false }
}

const handleRestore = async (role: Role) => {
  await restoreRole(role.id)
}

// Pagination
const handlePageChange = (page: number) => {
  fetchRoles(page)
}

// Search
const handleSearch = (search: string) => {
  updateFilters({ search })
}

// Status badge variant
const getStatusVariant = (status?: string) => {
  switch (status) {
    case 'active':
      return 'default'
    case 'inactive':
      return 'secondary'
    case 'deleted':
      return 'destructive'
    default:
      return 'outline'
  }
}

// Is role soft deleted
const isDeleted = (role: Role) => {
  return role.deleted_at !== null || role.status === 'deleted'
}

// Breadcrumbs
const breadcrumbs = [
  { label: 'Access Control' },
  { label: 'Roles' },
]
</script>

<template>
  <div class="container mx-auto py-6 space-y-6">
    <Head title="Roles Management" />

    <!-- Page Header -->
    <PageHeader
      title="Roles"
      description="Manage roles and their permissions"
      :breadcrumbs="breadcrumbs"
    >
      <template #actions>
        <Button @click="navigateToCreate">
          <PlusIcon class="mr-2 h-4 w-4" />
          Create Role
        </Button>
      </template>
    </PageHeader>

    <!-- Stats Cards -->
    <div class="grid gap-4 md:grid-cols-4">
      <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium">Total Roles</CardTitle>
          <ShieldIcon class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div v-if="isLoading" class="space-y-2">
            <Skeleton class="h-7 w-16" />
          </div>
          <div v-else class="text-2xl font-bold">{{ stats?.total || 0 }}</div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium">Active</CardTitle>
          <ShieldIcon class="h-4 w-4 text-green-500" />
        </CardHeader>
        <CardContent>
          <div v-if="isLoading" class="space-y-2">
            <Skeleton class="h-7 w-16" />
          </div>
          <div v-else class="text-2xl font-bold text-green-600">{{ stats?.active || 0 }}</div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium">With Permissions</CardTitle>
          <UsersIcon class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div v-if="isLoading" class="space-y-2">
            <Skeleton class="h-7 w-16" />
          </div>
          <div v-else class="text-2xl font-bold">{{ stats?.with_permissions || 0 }}</div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium">Inactive</CardTitle>
          <AlertTriangleIcon class="h-4 w-4 text-yellow-500" />
        </CardHeader>
        <CardContent>
          <div v-if="isLoading" class="space-y-2">
            <Skeleton class="h-7 w-16" />
          </div>
          <div v-else class="text-2xl font-bold text-yellow-600">{{ stats?.inactive || 0 }}</div>
        </CardContent>
      </Card>
    </div>

    <!-- Filters -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <SearchInput
        :model-value="filters.search || ''"
        placeholder="Search roles..."
        class="w-full md:w-80"
        @update:model-value="handleSearch"
      />
    </div>

    <!-- Data Table -->
    <div class="rounded-md border">
      <!-- Loading State -->
      <DataTableSkeleton v-if="isLoading" :columns="5" :rows="5" />

      <!-- Empty State -->
      <EmptyState
        v-else-if="roles.length === 0"
        title="No roles found"
        description="Get started by creating your first role."
        action-label="Create Role"
        @action="navigateToCreate"
      >
        <template #icon>
          <ShieldIcon class="h-8 w-8 text-muted-foreground" />
        </template>
      </EmptyState>

      <!-- Table -->
      <Table v-else>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Guard</TableHead>
            <TableHead>Status</TableHead>
            <TableHead class="text-center">Permissions</TableHead>
            <TableHead class="text-center">Users</TableHead>
            <TableHead class="text-right">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="role in roles"
            :key="role.id"
            :class="{ 'opacity-50': isDeleted(role) }"
          >
            <TableCell class="font-medium">
              {{ role.name }}
              <div v-if="role.description" class="text-sm text-muted-foreground">
                {{ typeof role.description === 'string' ? role.description : role.description?.en }}
              </div>
            </TableCell>
            <TableCell>
              <Badge variant="outline">{{ role.guard_name }}</Badge>
            </TableCell>
            <TableCell>
              <Badge :variant="getStatusVariant(role.status)">
                {{ role.status || 'active' }}
              </Badge>
            </TableCell>
            <TableCell class="text-center">
              {{ role.permissions_count || 0 }}
            </TableCell>
            <TableCell class="text-center">
              {{ role.users_count || 0 }}
            </TableCell>
            <TableCell class="text-right">
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button variant="ghost" size="icon">
                    <MoreHorizontalIcon class="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem @click="navigateToShow(role)">
                    <EyeIcon class="mr-2 h-4 w-4" />
                    View
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="!isDeleted(role)" @click="navigateToEdit(role)">
                    <PencilIcon class="mr-2 h-4 w-4" />
                    Edit
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    v-if="isDeleted(role)"
                    @click="handleRestore(role)"
                  >
                    <RotateCcwIcon class="mr-2 h-4 w-4" />
                    Restore
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    v-if="!isDeleted(role)"
                    class="text-destructive"
                    @click="openDeleteDialog(role, false)"
                  >
                    <Trash2Icon class="mr-2 h-4 w-4" />
                    Delete
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    v-if="isDeleted(role)"
                    class="text-destructive"
                    @click="openDeleteDialog(role, true)"
                  >
                    <Trash2Icon class="mr-2 h-4 w-4" />
                    Delete Permanently
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="flex items-center justify-between">
      <div class="text-sm text-muted-foreground">
        Showing {{ meta.from }} - {{ meta.to }} of {{ meta.total }}
      </div>
      <div class="flex gap-2">
        <Button
          variant="outline"
          size="sm"
          :disabled="meta.current_page === 1"
          @click="handlePageChange(meta.current_page - 1)"
        >
          Previous
        </Button>
        <Button
          variant="outline"
          size="sm"
          :disabled="meta.current_page === meta.last_page"
          @click="handlePageChange(meta.current_page + 1)"
        >
          Next
        </Button>
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <ConfirmDialog
      v-model:open="deleteDialog.open"
      :title="deleteDialog.force ? 'Permanently Delete Role' : 'Delete Role'"
      :description="deleteDialog.force
        ? `Are you sure you want to permanently delete '${deleteDialog.role?.name}'? This action cannot be undone.`
        : `Are you sure you want to delete '${deleteDialog.role?.name}'? You can restore it later.`"
      :confirm-label="deleteDialog.force ? 'Delete Permanently' : 'Delete'"
      variant="destructive"
      :loading="isSaving"
      @confirm="handleDelete"
    />
  </div>
</template>

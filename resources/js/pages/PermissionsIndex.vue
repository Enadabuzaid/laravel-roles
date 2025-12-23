<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import { PlusIcon, HomeIcon, GridIcon } from 'lucide-vue-next'
import { usePermissionsApi } from '@/laravel-roles/composables/usePermissionsApi'
import ViewToggle from '@/laravel-roles/components/ViewToggle.vue'
import FiltersBar from '@/laravel-roles/components/FiltersBar.vue'
import PermissionStatsCards from '@/laravel-roles/components/PermissionStatsCards.vue'
import PermissionTable from '@/laravel-roles/components/PermissionTable.vue'
import type { ViewMode, Permission } from '@/laravel-roles/types'

// Composable
const {
  permissions,
  stats,
  meta,
  filters,
  isLoading,
  isEmpty,
  fetchPermissions,
  fetchStats,
  deletePermission,
  restorePermission,
  updateFilters
} = usePermissionsApi()

// State
const viewMode = ref<ViewMode>('table')

// Lifecycle
onMounted(async () => {
  await Promise.all([
    fetchPermissions(),
    fetchStats()
  ])
})

// Methods
const handleView = (permission: Permission) => {
  // TODO: Navigate to permission show page
  router.visit(`/admin/acl/permissions/${permission.id}`)
}

const handleEdit = (permission: Permission) => {
  // TODO: Navigate to permission edit page
  router.visit(`/admin/acl/permissions/${permission.id}/edit`)
}

const handleCreate = () => {
  // TODO: Navigate to permission create page
  router.visit('/admin/acl/permissions/create')
}

const handleDelete = async (permission: Permission) => {
  if (confirm($t('roles.permissions.confirm.delete', { name: permission.name }))) {
    try {
      await deletePermission(permission.id)
      // TODO: Show success toast
    } catch (error) {
      // TODO: Show error toast
      console.error('Failed to delete permission:', error)
    }
  }
}

const handleRestore = async (permission: Permission) => {
  try {
    await restorePermission(permission.id)
    // TODO: Show success toast
  } catch (error) {
    // TODO: Show error toast
    console.error('Failed to restore permission:', error)
  }
}

const handleMatrix = () => {
  router.visit('/admin/acl/permissions/matrix')
}

const handlePageChange = (page: number) => {
  fetchPermissions(page)
}
</script>

<template>
  <div class="container mx-auto py-6 space-y-6">
    <Head :title="$t('roles.permissions.title')" />

    <!-- Breadcrumbs -->
    <Breadcrumb>
      <BreadcrumbList>
        <BreadcrumbItem>
          <BreadcrumbLink href="/admin">
            <HomeIcon class="h-4 w-4" />
          </BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <BreadcrumbPage>{{ $t('roles.permissions.title') }}</BreadcrumbPage>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Page Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-3xl font-bold tracking-tight">
          {{ $t('roles.permissions.title') }}
        </h1>
        <p class="text-muted-foreground">
          {{ $t('roles.permissions.subtitle') }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button variant="outline" @click="handleMatrix">
          <GridIcon class="mr-2 h-4 w-4" />
          {{ $t('roles.matrix.view') }}
        </Button>
        <ViewToggle v-model="viewMode" />
        <Button @click="handleCreate">
          <PlusIcon class="mr-2 h-4 w-4" />
          {{ $t('roles.permissions.create') }}
        </Button>
      </div>
    </div>

    <!-- Stats Cards -->
    <PermissionStatsCards :stats="stats" :loading="isLoading" />

    <!-- Filters -->
    <FiltersBar
      type="permissions"
      :model-value="filters"
      @update:model-value="updateFilters"
    />

    <!-- Content -->
    <div class="space-y-4">
      <!-- Table View -->
      <PermissionTable
        v-if="viewMode === 'table'"
        :permissions="permissions"
        :loading="isLoading"
        @view="handleView"
        @edit="handleEdit"
        @delete="handleDelete"
        @restore="handleRestore"
      />

      <!-- Grid View -->
      <!-- TODO: Create PermissionGrid component similar to RoleGrid -->
      <div v-else class="text-center py-12 text-muted-foreground">
        {{ $t('roles.grid_view_coming_soon') }}
      </div>

      <!-- Pagination -->
      <div v-if="!isEmpty && meta.last_page > 1" class="flex items-center justify-between">
        <div class="text-sm text-muted-foreground">
          {{ $t('common.showing') }}
          {{ meta.from }} - {{ meta.to }}
          {{ $t('common.of') }}
          {{ meta.total }}
        </div>
        <div class="flex gap-2">
          <Button
            variant="outline"
            size="sm"
            :disabled="meta.current_page === 1"
            @click="handlePageChange(meta.current_page - 1)"
          >
            {{ $t('common.previous') }}
          </Button>
          <Button
            variant="outline"
            size="sm"
            :disabled="meta.current_page === meta.last_page"
            @click="handlePageChange(meta.current_page + 1)"
          >
            {{ $t('common.next') }}
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>


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
import { PlusIcon, HomeIcon } from 'lucide-vue-next'
import { useRolesApi } from '@/composables/useRolesApi'
import ViewToggle from '@/components/ViewToggle.vue'
import FiltersBar from '@/components/FiltersBar.vue'
import RoleStatsCards from '@/components/RoleStatsCards.vue'
import RoleTable from '@/components/RoleTable.vue'
import RoleGrid from '@/components/RoleGrid.vue'
import type { ViewMode, Role } from '@/types'

// Composable
const {
  roles,
  stats,
  meta,
  filters,
  isLoading,
  isEmpty,
  fetchRoles,
  fetchStats,
  deleteRole,
  restoreRole,
  updateFilters
} = useRolesApi()

// State
const viewMode = ref<ViewMode>('table')

// Lifecycle
onMounted(async () => {
  await Promise.all([
    fetchRoles(),
    fetchStats()
  ])
})

// Methods
const handleView = (role: Role) => {
  // TODO: Navigate to role show page
  router.visit(`/admin/acl/roles/${role.id}`)
}

const handleEdit = (role: Role) => {
  // TODO: Navigate to role edit page
  router.visit(`/admin/acl/roles/${role.id}/edit`)
}

const handleCreate = () => {
  // TODO: Navigate to role create page
  router.visit('/admin/acl/roles/create')
}

const handleDelete = async (role: Role) => {
  if (confirm($t('roles.confirm.delete', { name: role.name }))) {
    try {
      await deleteRole(role.id)
      // TODO: Show success toast
    } catch (error) {
      // TODO: Show error toast
      console.error('Failed to delete role:', error)
    }
  }
}

const handleRestore = async (role: Role) => {
  try {
    await restoreRole(role.id)
    // TODO: Show success toast
  } catch (error) {
    // TODO: Show error toast
    console.error('Failed to restore role:', error)
  }
}

const handleClone = (role: Role) => {
  // TODO: Navigate to role clone page with pre-filled data
  router.visit(`/admin/acl/roles/${role.id}/clone`)
}

const handlePageChange = (page: number) => {
  fetchRoles(page)
}
</script>

<template>
  <div class="container mx-auto py-6 space-y-6">
    <Head :title="$t('roles.roles.title')" />

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
          <BreadcrumbPage>{{ $t('roles.roles.title') }}</BreadcrumbPage>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Page Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-3xl font-bold tracking-tight">
          {{ $t('roles.roles.title') }}
        </h1>
        <p class="text-muted-foreground">
          {{ $t('roles.roles.subtitle') }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <ViewToggle v-model="viewMode" />
        <Button @click="handleCreate">
          <PlusIcon class="mr-2 h-4 w-4" />
          {{ $t('roles.create') }}
        </Button>
      </div>
    </div>

    <!-- Stats Cards -->
    <RoleStatsCards :stats="stats" :loading="isLoading" />

    <!-- Filters -->
    <FiltersBar
      type="roles"
      :model-value="filters"
      @update:model-value="updateFilters"
    />

    <!-- Content -->
    <div class="space-y-4">
      <!-- Table View -->
      <RoleTable
        v-if="viewMode === 'table'"
        :roles="roles"
        :loading="isLoading"
        @view="handleView"
        @edit="handleEdit"
        @delete="handleDelete"
        @restore="handleRestore"
        @clone="handleClone"
      />

      <!-- Grid View -->
      <RoleGrid
        v-else
        :roles="roles"
        :loading="isLoading"
        @view="handleView"
        @edit="handleEdit"
        @delete="handleDelete"
        @restore="handleRestore"
        @clone="handleClone"
      />

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


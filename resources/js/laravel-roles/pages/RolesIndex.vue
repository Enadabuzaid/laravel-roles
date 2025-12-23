<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import LaravelRolesLayout from '../layouts/LaravelRolesLayout.vue'
import LrPageHeader from '../components/LrPageHeader.vue'
import LrStatsCards from '../components/LrStatsCards.vue'
import LrEmptyState from '../components/LrEmptyState.vue'
import {
  LrButton,
  LrInput,
  LrCard,
  LrTable,
  LrTableHeader,
  LrTableBody,
  LrTableRow,
  LrTableHead,
  LrTableCell,
  LrBadge,
  LrSkeleton,
  LrDialog,
  LrSelect,
} from '../ui'

// Types
interface Role {
  id: number
  name: string
  guard_name: string
  description?: string
  permissions_count?: number
  users_count?: number
  status?: string
  created_at: string
  deleted_at?: string | null
}

interface RoleStats {
  total: number
  with_permissions: number
  without_permissions: number
  active: number
}

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

// State
const roles = ref<Role[]>([])
const stats = ref<RoleStats | null>(null)
const meta = ref<PaginationMeta | null>(null)
const loading = ref(true)
const searchQuery = ref('')
const selectedGuard = ref('')
const showDeleteDialog = ref(false)
const roleToDelete = ref<Role | null>(null)
const deleting = ref(false)

// Get API prefix from config
const getApiUrl = (path: string) => {
  const prefix = (window as any).laravelRoles?.apiPrefix || 'admin/acl'
  return `/${prefix}${path}`
}

// Fetch roles
const fetchRoles = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (searchQuery.value) params.append('search', searchQuery.value)
    if (selectedGuard.value) params.append('guard', selectedGuard.value)

    const response = await fetch(getApiUrl(`/roles?${params.toString()}`), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (!response.ok) throw new Error('Failed to fetch roles')

    const data = await response.json()
    roles.value = data.data || data
    meta.value = data.meta || null
  } catch (error) {
    console.error('Error fetching roles:', error)
  } finally {
    loading.value = false
  }
}

// Fetch stats
const fetchStats = async () => {
  try {
    const response = await fetch(getApiUrl('/roles-stats'), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (!response.ok) throw new Error('Failed to fetch stats')

    const data = await response.json()
    stats.value = data.data || data
  } catch (error) {
    console.error('Error fetching stats:', error)
  }
}

// Delete role
const confirmDelete = (role: Role) => {
  roleToDelete.value = role
  showDeleteDialog.value = true
}

const deleteRole = async () => {
  if (!roleToDelete.value) return

  deleting.value = true
  try {
    const response = await fetch(getApiUrl(`/roles/${roleToDelete.value.id}`), {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
    })

    if (!response.ok) throw new Error('Failed to delete role')

    // Show success toast
    const toast = (window as any).__lr_toast
    if (toast) toast.success('Role deleted successfully')

    // Refresh data
    await fetchRoles()
    await fetchStats()
  } catch (error) {
    console.error('Error deleting role:', error)
    const toast = (window as any).__lr_toast
    if (toast) toast.error('Failed to delete role')
  } finally {
    deleting.value = false
    showDeleteDialog.value = false
    roleToDelete.value = null
  }
}

// Stats cards data
const statsCards = computed(() => [
  {
    title: 'Total Roles',
    value: stats.value?.total || 0,
    icon: 'shield' as const,
    color: 'text-blue-600',
  },
  {
    title: 'With Permissions',
    value: stats.value?.with_permissions || 0,
    icon: 'activity' as const,
    color: 'text-green-600',
  },
  {
    title: 'Without Permissions',
    value: stats.value?.without_permissions || 0,
    icon: 'shield' as const,
    color: 'text-orange-600',
  },
  {
    title: 'Active',
    value: stats.value?.active || stats.value?.total || 0,
    icon: 'users' as const,
    color: 'text-purple-600',
  },
])

// Watch for search changes
let searchTimeout: number
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = window.setTimeout(() => {
    fetchRoles()
  }, 300)
})

watch(selectedGuard, () => {
  fetchRoles()
})

// Initial fetch
onMounted(() => {
  fetchRoles()
  fetchStats()
})

// Navigate to create
const navigateToCreate = () => {
  window.location.href = getApiUrl('/roles/create').replace('/roles/create', '') + '/roles/create'
}

// Navigate to edit
const navigateToEdit = (id: number) => {
  const prefix = (window as any).laravelRoles?.uiPrefix || 'admin/acl'
  window.location.href = `/${prefix}/roles/${id}/edit`
}

// Guards for select
const guardOptions = [
  { value: '', label: 'All Guards' },
  { value: 'web', label: 'Web' },
  { value: 'api', label: 'API' },
]

// Breadcrumbs
const breadcrumbs = [
  { label: 'Dashboard', href: '/' },
  { label: 'Roles' },
]
</script>

<template>
  <LaravelRolesLayout title="Roles" description="Manage user roles and their permissions">
    <div class="space-y-6">
      <!-- Page Header -->
      <LrPageHeader
        title="Roles"
        description="Manage user roles and their permissions"
        :breadcrumbs="breadcrumbs"
      >
        <template #actions>
          <LrButton @click="navigateToCreate">
            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Create Role
          </LrButton>
        </template>
      </LrPageHeader>

      <!-- Stats Cards -->
      <LrStatsCards :stats="statsCards" :loading="loading" />

      <!-- Filters -->
      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1 relative">
          <svg
            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <LrInput
            v-model="searchQuery"
            placeholder="Search roles..."
            class="pl-10"
          />
        </div>
        <LrSelect
          v-model="selectedGuard"
          :options="guardOptions"
          placeholder="Filter by guard"
          class="w-full sm:w-48"
        />
      </div>

      <!-- Table -->
      <LrCard>
        <!-- Loading State -->
        <div v-if="loading" class="p-6">
          <div class="space-y-4">
            <LrSkeleton class="h-12 w-full" />
            <LrSkeleton class="h-12 w-full" />
            <LrSkeleton class="h-12 w-full" />
            <LrSkeleton class="h-12 w-full" />
          </div>
        </div>

        <!-- Empty State -->
        <LrEmptyState
          v-else-if="roles.length === 0"
          icon="shield"
          title="No roles found"
          description="Get started by creating your first role."
          action-label="Create Role"
          @action="navigateToCreate"
        />

        <!-- Data Table -->
        <LrTable v-else>
          <LrTableHeader>
            <LrTableRow>
              <LrTableHead>Name</LrTableHead>
              <LrTableHead>Guard</LrTableHead>
              <LrTableHead>Permissions</LrTableHead>
              <LrTableHead>Users</LrTableHead>
              <LrTableHead>Status</LrTableHead>
              <LrTableHead class="text-right">Actions</LrTableHead>
            </LrTableRow>
          </LrTableHeader>
          <LrTableBody>
            <LrTableRow v-for="role in roles" :key="role.id">
              <LrTableCell>
                <div class="font-medium">{{ role.name }}</div>
                <div v-if="role.description" class="text-sm text-muted-foreground">
                  {{ role.description }}
                </div>
              </LrTableCell>
              <LrTableCell>
                <LrBadge variant="outline">{{ role.guard_name }}</LrBadge>
              </LrTableCell>
              <LrTableCell>{{ role.permissions_count || 0 }}</LrTableCell>
              <LrTableCell>{{ role.users_count || 0 }}</LrTableCell>
              <LrTableCell>
                <LrBadge :variant="role.deleted_at ? 'destructive' : 'default'">
                  {{ role.deleted_at ? 'Deleted' : 'Active' }}
                </LrBadge>
              </LrTableCell>
              <LrTableCell class="text-right">
                <div class="flex justify-end gap-2">
                  <LrButton
                    variant="ghost"
                    size="sm"
                    @click="navigateToEdit(role.id)"
                  >
                    Edit
                  </LrButton>
                  <LrButton
                    variant="ghost"
                    size="sm"
                    @click="confirmDelete(role)"
                  >
                    Delete
                  </LrButton>
                </div>
              </LrTableCell>
            </LrTableRow>
          </LrTableBody>
        </LrTable>

        <!-- Pagination -->
        <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between p-4 border-t">
          <div class="text-sm text-muted-foreground">
            Showing {{ (meta.current_page - 1) * meta.per_page + 1 }} to 
            {{ Math.min(meta.current_page * meta.per_page, meta.total) }} of 
            {{ meta.total }} results
          </div>
          <div class="flex gap-2">
            <LrButton
              variant="outline"
              size="sm"
              :disabled="meta.current_page === 1"
            >
              Previous
            </LrButton>
            <LrButton
              variant="outline"
              size="sm"
              :disabled="meta.current_page === meta.last_page"
            >
              Next
            </LrButton>
          </div>
        </div>
      </LrCard>
    </div>

    <!-- Delete Dialog -->
    <LrDialog
      v-model:open="showDeleteDialog"
      title="Delete Role"
      :description="`Are you sure you want to delete '${roleToDelete?.name}'? This action cannot be undone.`"
      confirm-label="Delete"
      :loading="deleting"
      destructive
      @confirm="deleteRole"
    />
  </LaravelRolesLayout>
</template>

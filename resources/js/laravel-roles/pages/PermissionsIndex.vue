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
  LrSelect,
} from '../ui'

// Types
interface Permission {
  id: number
  name: string
  guard_name: string
  group?: string
  label?: string
  description?: string
  created_at: string
  deleted_at?: string | null
}

interface PermissionStats {
  total: number
  by_group?: Record<string, number>
  assigned_to_roles?: number
  not_assigned?: number
}

// State
const permissions = ref<Permission[]>([])
const stats = ref<PermissionStats | null>(null)
const loading = ref(true)
const searchQuery = ref('')
const selectedGuard = ref('')
const selectedGroup = ref('')

// Get API URL
const getApiUrl = (path: string) => {
  const prefix = (window as any).laravelRoles?.apiPrefix || 'admin/acl'
  return `/${prefix}${path}`
}

// Fetch permissions
const fetchPermissions = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (searchQuery.value) params.append('search', searchQuery.value)
    if (selectedGuard.value) params.append('guard', selectedGuard.value)
    if (selectedGroup.value) params.append('group', selectedGroup.value)

    const response = await fetch(getApiUrl(`/permissions?${params.toString()}`), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (!response.ok) throw new Error('Failed to fetch permissions')

    const data = await response.json()
    permissions.value = data.data || data
  } catch (error) {
    console.error('Error fetching permissions:', error)
  } finally {
    loading.value = false
  }
}

// Fetch stats
const fetchStats = async () => {
  try {
    const response = await fetch(getApiUrl('/permissions-stats'), {
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

// Stats cards
const statsCards = computed(() => [
  {
    title: 'Total Permissions',
    value: stats.value?.total || 0,
    icon: 'key' as const,
    color: 'text-blue-600',
  },
  {
    title: 'Permission Groups',
    value: Object.keys(stats.value?.by_group || {}).length,
    icon: 'layers' as const,
    color: 'text-purple-600',
  },
  {
    title: 'Assigned to Roles',
    value: stats.value?.assigned_to_roles || 0,
    icon: 'link' as const,
    color: 'text-green-600',
  },
  {
    title: 'Not Assigned',
    value: stats.value?.not_assigned || 0,
    icon: 'unlink' as const,
    color: 'text-orange-600',
  },
])

// Group permissions
const groupedPermissions = computed(() => {
  const groups: Record<string, Permission[]> = {}
  for (const perm of permissions.value) {
    const group = perm.group || 'other'
    if (!groups[group]) groups[group] = []
    groups[group].push(perm)
  }
  return groups
})

// Available groups for filter
const groupOptions = computed(() => {
  const groups = [...new Set(permissions.value.map(p => p.group || 'other'))]
  return [
    { value: '', label: 'All Groups' },
    ...groups.map(g => ({ value: g, label: g.charAt(0).toUpperCase() + g.slice(1) })),
  ]
})

// Watch for filter changes
let searchTimeout: number
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = window.setTimeout(() => {
    fetchPermissions()
  }, 300)
})

watch([selectedGuard, selectedGroup], () => {
  fetchPermissions()
})

// Guard options
const guardOptions = [
  { value: '', label: 'All Guards' },
  { value: 'web', label: 'Web' },
  { value: 'api', label: 'API' },
]

// Breadcrumbs
const breadcrumbs = [
  { label: 'Dashboard', href: '/' },
  { label: 'Permissions' },
]

// Initial fetch
onMounted(() => {
  fetchPermissions()
  fetchStats()
})
</script>

<template>
  <LaravelRolesLayout title="Permissions" description="View all available permissions">
    <div class="space-y-6">
      <!-- Page Header -->
      <LrPageHeader
        title="Permissions"
        description="View all available permissions in your application"
        :breadcrumbs="breadcrumbs"
      />

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
            placeholder="Search permissions..."
            class="pl-10"
          />
        </div>
        <LrSelect
          v-model="selectedGuard"
          :options="guardOptions"
          placeholder="Filter by guard"
          class="w-full sm:w-48"
        />
        <LrSelect
          v-model="selectedGroup"
          :options="groupOptions"
          placeholder="Filter by group"
          class="w-full sm:w-48"
        />
      </div>

      <!-- Content -->
      <LrCard>
        <!-- Loading State -->
        <div v-if="loading" class="p-6">
          <div class="space-y-4">
            <LrSkeleton class="h-12 w-full" />
            <LrSkeleton class="h-12 w-full" />
            <LrSkeleton class="h-12 w-full" />
          </div>
        </div>

        <!-- Empty State -->
        <LrEmptyState
          v-else-if="permissions.length === 0"
          icon="key"
          title="No permissions found"
          description="Run `php artisan roles:sync` to seed permissions from your config."
        />

        <!-- Grouped View -->
        <div v-else class="divide-y">
          <div v-for="(perms, group) in groupedPermissions" :key="group" class="p-4">
            <div class="flex items-center gap-2 mb-3">
              <h3 class="font-semibold capitalize">{{ group }}</h3>
              <LrBadge variant="secondary">{{ perms.length }}</LrBadge>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
              <div
                v-for="perm in perms"
                :key="perm.id"
                class="p-3 rounded-md border hover:bg-muted/50"
              >
                <div class="font-medium text-sm">{{ perm.name }}</div>
                <div v-if="perm.label" class="text-xs text-muted-foreground">
                  {{ perm.label }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </LrCard>
    </div>
  </LaravelRolesLayout>
</template>

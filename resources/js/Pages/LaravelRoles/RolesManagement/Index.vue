<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import StatsCard from '../shared/StatsCard.vue'
import ActionCard from '../shared/ActionCard.vue'
import Toast from '../shared/Toast.vue'
import RecentRoles from './partials/RecentRoles.vue'
import QuickActions from './partials/QuickActions.vue'

interface Props {
  config: {
    prefix: string  // UI routes prefix (e.g., /admin/acl/ui)
    apiPrefix: string  // API routes prefix (e.g., /admin/acl)
    guard: string
    layout?: string
  }
}
const props = defineProps<Props>()

// Layout configuration - pages will be wrapped by the app's layout
defineOptions({
  layout: (h: any, page: any) => {
    // Try to use the configured layout or fall back to default
    const layoutName = page.props?.config?.layout || 'AppLayout'
    try {
      // This allows the host app to provide their layout
      const AppLayout = require(`@/layouts/${layoutName}.vue`).default
      return h(AppLayout, () => page)
    } catch {
      // If no layout found, render page directly
      return page
    }
  },
})

const stats = ref({ total: 0, withPermissions: 0, withoutPermissions: 0, trashed: 0 })
const permStats = ref({ total: 0, groups: 0 })
const recentRoles = ref<any[]>([])
const loading = ref(true)

// Use apiPrefix for API calls, prefix for UI navigation
const apiPrefix = computed(() => props.config?.apiPrefix || props.config?.prefix?.replace('/ui', '') || 'admin/acl')
const uiPrefix = computed(() => props.config?.prefix || 'admin/acl/ui')

const apiUrl = (path: string) => `/${apiPrefix.value}${path}`
const uiUrl = (path: string) => `/${uiPrefix.value}${path}`

const fetchData = async () => {
  loading.value = true
  try {
    const headers = {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'Content-Type': 'application/json',
    }
    const [rolesRes, permsRes, recentRes] = await Promise.all([
      fetch(apiUrl('/roles-stats'), { headers, credentials: 'same-origin' }),
      fetch(apiUrl('/permissions-stats'), { headers, credentials: 'same-origin' }),
      fetch(apiUrl('/roles-recent'), { headers, credentials: 'same-origin' }),
    ])

    if (rolesRes.ok) {
      const d = await rolesRes.json()
      stats.value = {
        total: d.total || d.data?.total || 0,
        withPermissions: d.with_permissions || d.data?.with_permissions || 0,
        withoutPermissions: d.without_permissions || d.data?.without_permissions || 0,
        trashed: d.trashed || d.data?.trashed || 0,
      }
    }

    if (permsRes.ok) {
      const d = await permsRes.json()
      permStats.value = {
        total: d.total || d.data?.total || 0,
        groups: d.groups || d.data?.groups || 0,
      }
    }

    if (recentRes.ok) {
      const d = await recentRes.json()
      recentRoles.value = d.data || []
    }
  } catch (e) {
    console.error('Error fetching dashboard data:', e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)
</script>

<template>
  <Head title="Roles Management" />
  <Toast />
  <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-8">
    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-3xl font-bold tracking-tight">Roles Management</h1>
        <p class="mt-1 text-muted-foreground">Manage user roles, permissions, and access control</p>
      </div>
      <a
        :href="uiUrl('/roles/create')"
        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 shadow-lg shadow-primary/25 transition-all"
      >
        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Create Role
      </a>
    </div>

    <!-- Stats Cards -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <StatsCard
        title="Total Roles"
        :value="stats.total"
        icon="shield"
        color="bg-blue-500"
        :loading="loading"
        :href="uiUrl('/roles')"
      />
      <StatsCard
        title="Total Permissions"
        :value="permStats.total"
        icon="key"
        color="bg-purple-500"
        :loading="loading"
        :href="uiUrl('/permissions')"
      />
      <StatsCard
        title="With Permissions"
        :value="stats.withPermissions"
        icon="check"
        color="bg-green-500"
        :loading="loading"
      />
      <StatsCard
        title="Trashed"
        :value="stats.trashed"
        icon="trash"
        color="bg-red-500"
        :loading="loading"
      />
    </div>

    <!-- Quick Actions -->
    <QuickActions
      :api-prefix="apiPrefix"
      :ui-prefix="uiPrefix"
      :roles-count="stats.total"
      :permissions-count="permStats.total"
    />

    <!-- Action Cards -->
    <div class="grid gap-4 md:grid-cols-3">
      <ActionCard
        title="Manage Roles"
        description="View, create, edit, and delete user roles"
        :href="uiUrl('/roles')"
        icon="shield"
        color="bg-gradient-to-br from-blue-500 to-blue-600"
        :badge="stats.total"
      />
      <ActionCard
        title="Manage Permissions"
        description="View and organize system permissions"
        :href="uiUrl('/permissions')"
        icon="key"
        color="bg-gradient-to-br from-purple-500 to-purple-600"
        :badge="permStats.total"
      />
      <ActionCard
        title="Permission Matrix"
        description="Visual role-permission assignment"
        :href="uiUrl('/matrix')"
        icon="grid"
        color="bg-gradient-to-br from-indigo-500 to-indigo-600"
      />
    </div>

    <!-- Recent Roles -->
    <RecentRoles
      :roles="recentRoles"
      :loading="loading"
      :api-prefix="apiPrefix"
      :ui-prefix="uiPrefix"
      @refresh="fetchData"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import StatsCard from '../shared/StatsCard.vue'
import ActionCard from '../shared/ActionCard.vue'
import Toast from '../shared/Toast.vue'
import RecentRoles from './partials/RecentRoles.vue'
import QuickActions from './partials/QuickActions.vue'

interface Props { config: { prefix: string; guard: string } }
const props = defineProps<Props>()

const stats = ref({ total: 0, withPermissions: 0, withoutPermissions: 0, trashed: 0 })
const permStats = ref({ total: 0, groups: 0 })
const recentRoles = ref<any[]>([])
const loading = ref(true)

const apiPrefix = computed(() => props.config?.prefix || 'admin/acl')
const url = (path: string) => `/${apiPrefix.value}${path}`

const fetchData = async () => {
  loading.value = true
  try {
    const [rolesRes, permsRes, recentRes] = await Promise.all([
      fetch(url('/roles-stats'), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }),
      fetch(url('/permissions-stats'), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }),
      fetch(url('/roles?limit=5'), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }),
    ])
    if (rolesRes.ok) { const d = await rolesRes.json(); stats.value = { total: d.total || d.data?.total || 0, withPermissions: d.with_permissions || 0, withoutPermissions: d.without_permissions || 0, trashed: d.trashed || 0 } }
    if (permsRes.ok) { const d = await permsRes.json(); permStats.value = { total: d.total || d.data?.total || 0, groups: d.groups || d.data?.groups || 0 } }
    if (recentRes.ok) { const d = await recentRes.json(); recentRoles.value = d.data || [] }
  } catch (e) { console.error(e) } finally { loading.value = false }
}
onMounted(fetchData)
</script>

<template>
  <Head title="Roles Management" />
  <Toast />
  <div class="space-y-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-3xl font-bold tracking-tight">Roles Management</h1>
        <p class="mt-1 text-muted-foreground">Manage user roles, permissions, and access control</p>
      </div>
      <a :href="url('/roles/create')" class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 shadow-lg shadow-primary/25 transition-all">
        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
        Create Role
      </a>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <StatsCard title="Total Roles" :value="stats.total" icon="shield" color="bg-blue-500" :loading="loading" :href="url('/roles')" />
      <StatsCard title="Total Permissions" :value="permStats.total" icon="key" color="bg-purple-500" :loading="loading" :href="url('/permissions')" />
      <StatsCard title="With Permissions" :value="stats.withPermissions" icon="check" color="bg-green-500" :loading="loading" />
      <StatsCard title="Trashed" :value="stats.trashed" icon="trash" color="bg-red-500" :loading="loading" />
    </div>
    <QuickActions :api-prefix="apiPrefix" :roles-count="stats.total" :permissions-count="permStats.total" />
    <div class="grid gap-4 md:grid-cols-3">
      <ActionCard title="Manage Roles" description="View, create, edit, and delete user roles" :href="url('/roles')" icon="shield" color="bg-gradient-to-br from-blue-500 to-blue-600" :badge="stats.total" />
      <ActionCard title="Manage Permissions" description="View and organize system permissions" :href="url('/permissions')" icon="key" color="bg-gradient-to-br from-purple-500 to-purple-600" :badge="permStats.total" />
      <ActionCard title="Permission Matrix" description="Visual role-permission assignment" :href="url('/matrix')" icon="grid" color="bg-gradient-to-br from-indigo-500 to-indigo-600" />
    </div>
    <RecentRoles :roles="recentRoles" :loading="loading" :api-prefix="apiPrefix" @refresh="fetchData" />
  </div>
</template>

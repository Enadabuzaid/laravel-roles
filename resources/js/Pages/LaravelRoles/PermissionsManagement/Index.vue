<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import StatsCard from '../shared/StatsCard.vue'
import ActionCard from '../shared/ActionCard.vue'
import Toast from '../shared/Toast.vue'
import RecentPermissions from './partials/RecentPermissions.vue'

interface Props { config: { prefix: string } }
const props = defineProps<Props>()

const stats = ref({ total: 0, groups: 0 }), roleStats = ref({ total: 0 }), recentPerms = ref<any[]>([]), loading = ref(true)
const apiPrefix = computed(() => props.config?.prefix || 'admin/acl')
const url = (p: string) => `/${apiPrefix.value}${p}`

const fetchData = async () => { loading.value = true; try { const [permsRes, rolesRes, recentRes] = await Promise.all([fetch(url('/permissions-stats'), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }), fetch(url('/roles-stats'), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }), fetch(url('/permissions?limit=8'), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })]); if (permsRes.ok) { const d = await permsRes.json(); stats.value = { total: d.total || d.data?.total || 0, groups: d.groups || d.data?.groups || 0 } }; if (rolesRes.ok) { const d = await rolesRes.json(); roleStats.value = { total: d.total || d.data?.total || 0 } }; if (recentRes.ok) { const d = await recentRes.json(); recentPerms.value = d.data || [] } } catch (e) { console.error(e) } finally { loading.value = false } }
onMounted(fetchData)
</script>

<template>
  <Head title="Permissions Management" /><Toast />
  <div class="space-y-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div><h1 class="text-3xl font-bold tracking-tight">Permissions Management</h1><p class="mt-1 text-muted-foreground">View, organize, and assign permissions to roles</p></div>
      <a :href="url('/matrix')" class="inline-flex items-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 shadow-lg shadow-primary/25"><svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>Open Matrix</a>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <StatsCard title="Total Permissions" :value="stats.total" icon="key" color="bg-purple-500" :loading="loading" :href="url('/permissions')" />
      <StatsCard title="Permission Groups" :value="stats.groups" icon="folder" color="bg-blue-500" :loading="loading" />
      <StatsCard title="Total Roles" :value="roleStats.total" icon="shield" color="bg-green-500" :loading="loading" :href="url('/roles')" />
      <StatsCard title="Matrix Assignments" value="Interactive" icon="grid" color="bg-indigo-500" :loading="loading" :href="url('/matrix')" />
    </div>
    <div class="grid gap-4 md:grid-cols-3">
      <ActionCard title="All Permissions" description="View and organize all system permissions" :href="url('/permissions')" icon="key" color="bg-gradient-to-br from-purple-500 to-purple-600" :badge="stats.total" />
      <ActionCard title="Permission Matrix" description="Visual role-permission assignment" :href="url('/matrix')" icon="grid" color="bg-gradient-to-br from-indigo-500 to-indigo-600" />
      <ActionCard title="Manage Roles" description="Back to roles management" :href="url('/roles')" icon="shield" color="bg-gradient-to-br from-blue-500 to-blue-600" :badge="roleStats.total" />
    </div>
    <div class="rounded-xl border bg-gradient-to-r from-purple-500/5 to-transparent p-6"><div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"><div><h3 class="font-semibold">Quick Overview</h3><p class="text-sm text-muted-foreground">You have <span class="font-medium text-foreground">{{ stats.total }}</span> permissions organized in <span class="font-medium text-foreground">{{ stats.groups }}</span> groups</p></div><div class="flex items-center gap-2"><a :href="url('/matrix')" class="inline-flex items-center gap-2 rounded-lg bg-purple-500/10 px-3 py-2 text-sm font-medium text-purple-600 hover:bg-purple-500/20 transition-colors dark:text-purple-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>Open Permission Matrix</a></div></div></div>
    <RecentPermissions :permissions="recentPerms" :loading="loading" :api-prefix="apiPrefix" />
  </div>
</template>

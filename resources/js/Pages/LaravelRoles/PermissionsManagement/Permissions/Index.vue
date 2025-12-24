<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import ViewToggle from '../../shared/ViewToggle.vue'
import Toast from '../../shared/Toast.vue'

interface Props {
  config: {
    prefix: string     // UI prefix
    apiPrefix: string  // API prefix
  }
}
const props = defineProps<Props>()

interface Permission {
  id: number
  name: string
  guard_name: string
  group: string
  label?: string
  roles_count?: number
}

const permissions = ref<Permission[]>([])
const groups = ref<string[]>([])
const loading = ref(true)
const viewMode = ref<'list' | 'grid'>('grid')
const searchQuery = ref('')
const groupFilter = ref('')
const guardFilter = ref('')

// Use separate API and UI prefixes
const apiPrefix = computed(() => props.config?.apiPrefix || props.config?.prefix?.replace('/ui', '') || 'admin/acl')
const uiPrefix = computed(() => props.config?.prefix || 'admin/acl/ui')

const apiUrl = (p: string) => `/${apiPrefix.value}${p}`
const uiUrl = (p: string) => `/${uiPrefix.value}${p}`

const fetchPerms = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (searchQuery.value) params.append('search', searchQuery.value)
    if (groupFilter.value) params.append('group', groupFilter.value)
    if (guardFilter.value) params.append('guard', guardFilter.value)

    const res = await fetch(apiUrl(`/permissions?${params}`), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (res.ok) {
      const d = await res.json()
      permissions.value = d.data || d || []
      groups.value = [...new Set(permissions.value.map(p => p.group || 'other'))].sort()
    }
  } catch (e) {
    console.error('Error fetching permissions:', e)
  } finally {
    loading.value = false
  }
}

const groupedPerms = computed(() => {
  const g: Record<string, Permission[]> = {}
  for (const p of permissions.value) {
    const k = p.group || 'other'
    if (!g[k]) g[k] = []
    g[k].push(p)
  }
  return g
})

let timeout: number
watch(searchQuery, () => {
  clearTimeout(timeout)
  timeout = window.setTimeout(fetchPerms, 300)
})
watch([groupFilter, guardFilter], fetchPerms)
onMounted(fetchPerms)
</script>

<template>
  <Head title="Permissions" />
  <Toast />
  <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <div class="flex items-center gap-2">
          <a :href="uiUrl('')" class="text-muted-foreground hover:text-foreground">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
          </a>
          <h1 class="text-3xl font-bold tracking-tight">Permissions</h1>
        </div>
        <p class="mt-1 text-muted-foreground">View and organize system permissions</p>
      </div>
      <a
        :href="uiUrl('/matrix')"
        class="inline-flex items-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 shadow-lg shadow-primary/25"
      >
        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
        </svg>
        Permission Matrix
      </a>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
      <div class="relative flex-1 max-w-sm">
        <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search permissions..."
          class="h-10 w-full rounded-lg border bg-background pl-10 pr-4 text-sm"
        />
      </div>
      <select v-model="groupFilter" class="h-10 rounded-lg border bg-background px-3 text-sm">
        <option value="">All Groups</option>
        <option v-for="g in groups" :key="g" :value="g">{{ g }}</option>
      </select>
      <select v-model="guardFilter" class="h-10 rounded-lg border bg-background px-3 text-sm">
        <option value="">All Guards</option>
        <option value="web">Web</option>
        <option value="api">API</option>
      </select>
      <ViewToggle v-model="viewMode" />
    </div>

    <!-- Content -->
    <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
      <!-- Loading -->
      <div v-if="loading" class="p-8 text-center">
        <svg class="mx-auto h-8 w-8 animate-spin text-primary" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <!-- Empty -->
      <div v-else-if="permissions.length === 0" class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-muted-foreground/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
        </svg>
        <p class="mt-3 text-lg font-medium">No permissions found</p>
        <p class="text-sm text-muted-foreground">Run: <code class="bg-muted px-1.5 py-0.5 rounded">php artisan roles:sync</code></p>
      </div>

      <!-- Grid View -->
      <div v-else-if="viewMode === 'grid'" class="p-4 space-y-4">
        <div v-for="(perms, group) in groupedPerms" :key="group" class="rounded-lg border">
          <div class="flex items-center gap-3 bg-muted/50 px-4 py-3">
            <svg class="h-5 w-5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
            </svg>
            <span class="font-semibold capitalize">{{ group }}</span>
            <span class="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-200">
              {{ perms.length }}
            </span>
          </div>
          <div class="grid gap-3 p-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            <div
              v-for="perm in perms"
              :key="perm.id"
              class="rounded-lg border bg-background p-3 hover:shadow-md transition-shadow"
            >
              <div class="flex items-start gap-2">
                <svg class="mt-0.5 h-4 w-4 text-purple-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium truncate">{{ perm.label || perm.name }}</p>
                </div>
              </div>
              <div class="mt-2 flex items-center gap-2">
                <span class="inline-flex items-center rounded border px-1.5 py-0.5 text-xs">{{ perm.guard_name }}</span>
                <span
                  v-if="perm.roles_count"
                  class="inline-flex items-center rounded-full bg-blue-100 px-1.5 py-0.5 text-xs text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                >
                  {{ perm.roles_count }} roles
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Table View -->
      <table v-else class="w-full">
        <thead class="border-b bg-muted/50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium">Permission</th>
            <th class="px-4 py-3 text-left text-sm font-medium">Group</th>
            <th class="px-4 py-3 text-left text-sm font-medium">Guard</th>
            <th class="px-4 py-3 text-left text-sm font-medium">Roles</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="perm in permissions" :key="perm.id" class="hover:bg-muted/50">
            <td class="px-4 py-3">
              <div class="font-medium">{{ perm.label || perm.name }}</div>
            </td>
            <td class="px-4 py-3">
              <span class="rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800 capitalize">
                {{ perm.group }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="rounded border px-2 py-0.5 text-xs">{{ perm.guard_name }}</span>
            </td>
            <td class="px-4 py-3">
              <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                {{ perm.roles_count || 0 }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

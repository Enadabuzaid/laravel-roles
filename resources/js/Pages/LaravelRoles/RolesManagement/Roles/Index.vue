<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import ViewToggle from '../../shared/ViewToggle.vue'
import Pagination from '../../shared/Pagination.vue'
import ConfirmDialog from '../../shared/ConfirmDialog.vue'
import Toast from '../../shared/Toast.vue'

interface Props {
  config: {
    prefix: string     // UI routes (e.g., admin/acl/ui)
    apiPrefix: string  // API routes (e.g., admin/acl)
  }
}
const props = defineProps<Props>()

interface Role {
  id: number
  name: string
  guard_name: string
  description?: string
  permissions_count: number
  users_count: number
  created_at: string
  deleted_at: string | null
}

const roles = ref<Role[]>([])
const meta = ref<any>(null)
const loading = ref(true)
const viewMode = ref<'list' | 'grid'>('list')
const searchQuery = ref('')
const guardFilter = ref('')
const trashedFilter = ref<'active' | 'with' | 'only'>('active')
const selectedIds = ref<number[]>([])
const showDelete = ref(false)
const showBulkDelete = ref(false)
const roleToDelete = ref<Role | null>(null)
const deleting = ref(false)
const currentPage = ref(1)

// Separate API and UI prefixes
const apiPrefix = computed(() => props.config?.apiPrefix || props.config?.prefix?.replace('/ui', '') || 'admin/acl')
const uiPrefix = computed(() => props.config?.prefix || 'admin/acl/ui')

const apiUrl = (p: string) => `/${apiPrefix.value}${p}`
const uiUrl = (p: string) => `/${uiPrefix.value}${p}`

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
const toast = (m: string, t: 'success' | 'error' = 'success') => {
  const x = (window as any).__lr_toast
  if (x) t === 'success' ? x.success(m) : x.error(m)
}

const fetchRoles = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (searchQuery.value) params.append('search', searchQuery.value)
    if (guardFilter.value) params.append('guard', guardFilter.value)
    if (trashedFilter.value === 'only') params.append('only_trashed', 'true')
    if (trashedFilter.value === 'with') params.append('with_trashed', 'true')
    params.append('page', currentPage.value.toString())

    const res = await fetch(apiUrl(`/roles?${params}`), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (res.ok) {
      const d = await res.json()
      roles.value = d.data || []
      meta.value = d.meta || null
    }
  } catch {
    toast('Failed to load', 'error')
  } finally {
    loading.value = false
  }
}

const confirmDelete = (r: Role) => {
  roleToDelete.value = r
  showDelete.value = true
}

const deleteRole = async () => {
  if (!roleToDelete.value) return
  deleting.value = true
  try {
    await fetch(apiUrl(`/roles/${roleToDelete.value.id}`), {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
    toast('Deleted')
    await fetchRoles()
  } catch {
    toast('Failed', 'error')
  } finally {
    deleting.value = false
    showDelete.value = false
  }
}

const restoreRole = async (r: Role) => {
  try {
    await fetch(apiUrl(`/roles/${r.id}/restore`), {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
    toast('Restored')
    await fetchRoles()
  } catch {
    toast('Failed', 'error')
  }
}

const bulkDelete = async () => {
  if (!selectedIds.value.length) return
  deleting.value = true
  try {
    await fetch(apiUrl('/roles/bulk-delete'), {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ ids: selectedIds.value }),
    })
    toast(`${selectedIds.value.length} deleted`)
    selectedIds.value = []
    await fetchRoles()
  } catch {
    toast('Failed', 'error')
  } finally {
    deleting.value = false
    showBulkDelete.value = false
  }
}

const toggleAll = () => {
  selectedIds.value = selectedIds.value.length === roles.value.length ? [] : roles.value.map(r => r.id)
}

const isAllSelected = computed(() => roles.value.length > 0 && selectedIds.value.length === roles.value.length)

let timeout: number
watch(searchQuery, () => {
  clearTimeout(timeout)
  timeout = window.setTimeout(() => {
    currentPage.value = 1
    fetchRoles()
  }, 300)
})

watch([guardFilter, trashedFilter], () => {
  currentPage.value = 1
  fetchRoles()
})

const formatDate = (d: string) => new Date(d).toLocaleDateString('en-US', {
  month: 'short',
  day: 'numeric',
  year: 'numeric',
})

onMounted(fetchRoles)
</script>

<template>
  <Head title="Roles" />
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
          <h1 class="text-3xl font-bold tracking-tight">Roles</h1>
        </div>
        <p class="mt-1 text-muted-foreground">Manage user roles and permissions</p>
      </div>
      <a
        :href="uiUrl('/roles/create')"
        class="inline-flex items-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 shadow-lg shadow-primary/25"
      >
        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Create Role
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
          placeholder="Search roles..."
          class="h-10 w-full rounded-lg border bg-background pl-10 pr-4 text-sm"
        />
      </div>
      <select v-model="guardFilter" class="h-10 rounded-lg border bg-background px-3 text-sm">
        <option value="">All Guards</option>
        <option value="web">Web</option>
        <option value="api">API</option>
      </select>
      <select v-model="trashedFilter" class="h-10 rounded-lg border bg-background px-3 text-sm">
        <option value="active">Active Only</option>
        <option value="with">With Trashed</option>
        <option value="only">Only Trashed</option>
      </select>
      <ViewToggle v-model="viewMode" />
      <button
        v-if="selectedIds.length"
        @click="showBulkDelete = true"
        class="h-10 px-4 rounded-lg bg-destructive text-destructive-foreground text-sm font-medium"
      >
        Delete ({{ selectedIds.length }})
      </button>
    </div>

    <!-- Table/Grid -->
    <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
      <!-- Loading -->
      <div v-if="loading" class="p-8 text-center">
        <svg class="mx-auto h-8 w-8 animate-spin text-primary" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <!-- Empty -->
      <div v-else-if="roles.length === 0" class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-muted-foreground/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        <p class="mt-3 text-lg font-medium">No roles found</p>
        <a :href="uiUrl('/roles/create')" class="mt-4 inline-flex items-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">
          Create Role
        </a>
      </div>

      <!-- List View -->
      <table v-else-if="viewMode === 'list'" class="w-full">
        <thead class="border-b bg-muted/50">
          <tr>
            <th class="w-12 px-4 py-3">
              <input type="checkbox" :checked="isAllSelected" @change="toggleAll" class="h-4 w-4 rounded" />
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium">Name</th>
            <th class="px-4 py-3 text-left text-sm font-medium">Guard</th>
            <th class="px-4 py-3 text-left text-sm font-medium">Perms</th>
            <th class="px-4 py-3 text-left text-sm font-medium">Users</th>
            <th class="px-4 py-3 text-left text-sm font-medium">Status</th>
            <th class="px-4 py-3 text-left text-sm font-medium">Created</th>
            <th class="px-4 py-3 text-right text-sm font-medium">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="r in roles" :key="r.id" class="hover:bg-muted/50" :class="{ 'bg-red-50/50 dark:bg-red-950/10': r.deleted_at }">
            <td class="px-4 py-3">
              <input type="checkbox" :value="r.id" v-model="selectedIds" class="h-4 w-4 rounded" />
            </td>
            <td class="px-4 py-3">
              <div class="font-medium">{{ r.name }}</div>
              <div v-if="r.description" class="text-xs text-muted-foreground truncate max-w-xs">{{ r.description }}</div>
            </td>
            <td class="px-4 py-3">
              <span class="rounded border px-2 py-0.5 text-xs">{{ r.guard_name }}</span>
            </td>
            <td class="px-4 py-3">
              <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-800">{{ r.permissions_count }}</span>
            </td>
            <td class="px-4 py-3">
              <span class="rounded-full bg-purple-100 px-2 py-0.5 text-xs text-purple-800">{{ r.users_count }}</span>
            </td>
            <td class="px-4 py-3">
              <span :class="['rounded-full px-2 py-0.5 text-xs', r.deleted_at ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800']">
                {{ r.deleted_at ? 'Trashed' : 'Active' }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-muted-foreground">{{ formatDate(r.created_at) }}</td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <template v-if="r.deleted_at">
                  <button @click="restoreRole(r)" class="p-2 rounded-lg text-green-600 hover:bg-green-100" title="Restore">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                    </svg>
                  </button>
                </template>
                <template v-else>
                  <a :href="uiUrl(`/roles/${r.id}/edit`)" class="p-2 rounded-lg hover:bg-muted" title="Edit">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </a>
                  <button @click="confirmDelete(r)" class="p-2 rounded-lg text-destructive hover:bg-destructive/10" title="Delete">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Grid View -->
      <div v-else class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="r in roles"
          :key="r.id"
          class="relative rounded-xl border p-4 hover:shadow-md transition-shadow"
          :class="{ 'border-red-200 bg-red-50/50': r.deleted_at }"
        >
          <div class="absolute top-3 right-3">
            <input type="checkbox" :value="r.id" v-model="selectedIds" class="h-4 w-4 rounded" />
          </div>
          <div class="flex items-center gap-3 mb-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
              <svg class="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <div>
              <h3 class="font-semibold">{{ r.name }}</h3>
              <p class="text-xs text-muted-foreground">{{ r.guard_name }}</p>
            </div>
          </div>
          <div class="flex items-center gap-2 mb-4">
            <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-800">{{ r.permissions_count }} perms</span>
            <span class="rounded-full bg-purple-100 px-2 py-0.5 text-xs text-purple-800">{{ r.users_count }} users</span>
          </div>
          <div class="flex items-center gap-2">
            <template v-if="r.deleted_at">
              <button @click="restoreRole(r)" class="flex-1 h-9 rounded-lg border text-sm font-medium text-green-600 hover:bg-green-50">
                Restore
              </button>
            </template>
            <template v-else>
              <a :href="uiUrl(`/roles/${r.id}/edit`)" class="flex-1 h-9 rounded-lg border text-sm font-medium flex items-center justify-center hover:bg-muted">
                Edit
              </a>
              <button @click="confirmDelete(r)" class="h-9 px-3 rounded-lg border text-destructive hover:bg-destructive/10">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </template>
          </div>
        </div>
      </div>

      <Pagination v-if="meta && meta.last_page > 1" :meta="meta" @page="p => { currentPage = p; fetchRoles() }" />
    </div>
  </div>

  <ConfirmDialog
    v-model:open="showDelete"
    title="Delete Role"
    :description="`Delete '${roleToDelete?.name}'?`"
    confirm-label="Delete"
    :loading="deleting"
    destructive
    @confirm="deleteRole"
  />
  <ConfirmDialog
    v-model:open="showBulkDelete"
    :title="`Delete ${selectedIds.length} Roles`"
    description="Are you sure?"
    confirm-label="Delete All"
    :loading="deleting"
    destructive
    @confirm="bulkDelete"
  />
</template>

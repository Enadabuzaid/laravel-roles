<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import ConfirmDialog from '../../shared/ConfirmDialog.vue'
import Toast from '../../shared/Toast.vue'

interface Props {
  config: {
    prefix: string     // UI prefix
    apiPrefix: string  // API prefix
  }
  roleId?: number
  guards?: string[]
}
const props = withDefaults(defineProps<Props>(), {
  guards: () => ['web', 'api'],
})

interface Role {
  id: number
  name: string
  guard_name: string
  description?: string
  permissions: any[]
  users_count: number
  created_at: string
  updated_at: string
}
interface Permission {
  id: number
  name: string
  group: string
  label?: string
}

const role = ref<Role | null>(null)
const form = ref({ name: '', description: '', guard_name: 'web' })
const selectedPerms = ref<number[]>([])
const allPerms = ref<Permission[]>([])
const errors = ref<Record<string, string[]>>({})
const loading = ref(true)
const saving = ref(false)
const activeTab = ref<'details' | 'permissions'>('details')
const permSearch = ref('')
const showDelete = ref(false)
const deleting = ref(false)

// Use separate API and UI prefixes
const apiPrefix = computed(() => props.config?.apiPrefix || props.config?.prefix?.replace('/ui', '') || 'admin/acl')
const uiPrefix = computed(() => props.config?.prefix || 'admin/acl/ui')

const apiUrl = (p: string) => `/${apiPrefix.value}${p}`
const uiUrl = (p: string) => `/${uiPrefix.value}${p}`

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
const toast = (m: string, t: 'success' | 'error' = 'success') => {
  const x = (window as any).__lr_toast
  if (x) t === 'success' ? x.success(m) : x.error(m)
}

const getRoleId = () => props.roleId || parseInt(window.location.pathname.match(/\/roles\/(\d+)/)?.[1] || '0')

const fetchRole = async () => {
  const id = getRoleId()
  if (!id) return
  loading.value = true
  try {
    const res = await fetch(apiUrl(`/roles/${id}`), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
    if (!res.ok) throw new Error()
    const d = await res.json()
    role.value = d.data || d
    form.value = {
      name: role.value!.name,
      description: role.value!.description || '',
      guard_name: role.value!.guard_name,
    }
    selectedPerms.value = (role.value!.permissions || []).map((p: any) => p.id)
  } catch {
    toast('Failed to load', 'error')
  } finally {
    loading.value = false
  }
}

const fetchPerms = async () => {
  try {
    const res = await fetch(apiUrl('/permissions'), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
    if (res.ok) {
      const d = await res.json()
      allPerms.value = d.data || d || []
    }
  } catch {
    // ignore
  }
}

const groupedPerms = computed(() => {
  const q = permSearch.value.toLowerCase()
  const f = allPerms.value.filter(p =>
    p.name.toLowerCase().includes(q) || (p.label?.toLowerCase().includes(q))
  )
  const g: Record<string, Permission[]> = {}
  for (const p of f) {
    const k = p.group || 'other'
    if (!g[k]) g[k] = []
    g[k].push(p)
  }
  return g
})

const togglePerm = async (id: number) => {
  if (!role.value) return
  const has = selectedPerms.value.includes(id)
  const perm = allPerms.value.find(p => p.id === id)
  if (!perm) return

  // Optimistic update
  if (has) {
    selectedPerms.value = selectedPerms.value.filter(p => p !== id)
  } else {
    selectedPerms.value.push(id)
  }

  try {
    const endpoint = has
      ? apiUrl(`/roles/${role.value.id}/permissions/${perm.name}`)
      : apiUrl(`/roles/${role.value.id}/permissions`)

    const res = await fetch(endpoint, {
      method: has ? 'DELETE' : 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: has ? undefined : JSON.stringify({ permission: perm.name }),
    })
    if (!res.ok) throw new Error()
  } catch {
    // Revert on failure
    if (has) {
      selectedPerms.value.push(id)
    } else {
      selectedPerms.value = selectedPerms.value.filter(p => p !== id)
    }
    toast('Failed', 'error')
  }
}

const toggleGroup = async (perms: Permission[]) => {
  if (!role.value) return
  const ids = perms.map(p => p.id)
  const all = ids.every(id => selectedPerms.value.includes(id))

  if (all) {
    selectedPerms.value = selectedPerms.value.filter(id => !ids.includes(id))
  } else {
    ids.forEach(id => {
      if (!selectedPerms.value.includes(id)) selectedPerms.value.push(id)
    })
  }

  try {
    const names = selectedPerms.value
      .map(id => allPerms.value.find(p => p.id === id)?.name)
      .filter(Boolean)

    const res = await fetch(apiUrl(`/roles/${role.value.id}/sync`), {
      method: 'PUT',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ permissions: names }),
    })
    if (!res.ok) throw new Error()
    toast('Updated')
  } catch {
    await fetchRole()
    toast('Failed', 'error')
  }
}

const isGroupSelected = (perms: Permission[]) =>
  perms.every(p => selectedPerms.value.includes(p.id))

const isGroupPartial = (perms: Permission[]) => {
  const s = perms.filter(p => selectedPerms.value.includes(p.id))
  return s.length > 0 && s.length < perms.length
}

const saveDetails = async () => {
  if (!role.value) return
  saving.value = true
  errors.value = {}
  try {
    const res = await fetch(apiUrl(`/roles/${role.value.id}`), {
      method: 'PUT',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify(form.value),
    })
    const d = await res.json()
    if (!res.ok) {
      if (res.status === 422) errors.value = d.errors || {}
      throw new Error()
    }
    toast('Updated')
    await fetchRole()
  } catch {
    toast('Failed', 'error')
  } finally {
    saving.value = false
  }
}

const deleteRole = async () => {
  if (!role.value) return
  deleting.value = true
  try {
    const res = await fetch(apiUrl(`/roles/${role.value.id}`), {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
    if (!res.ok) throw new Error()
    toast('Deleted')
    window.location.href = uiUrl('/roles')
  } catch {
    toast('Failed', 'error')
  } finally {
    deleting.value = false
    showDelete.value = false
  }
}

const formatDate = (d: string) =>
  new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })

onMounted(() => {
  fetchRole()
  fetchPerms()
})
</script>

<template>
  <Head :title="`Edit: ${role?.name || 'Loading...'}`" />
  <Toast />
  <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6 max-w-4xl">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <a :href="uiUrl('/roles')" class="p-2 rounded-lg hover:bg-muted">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
        </a>
        <div>
          <h1 class="text-2xl font-bold">
            Edit Role<span v-if="role">: {{ role.name }}</span>
          </h1>
          <p class="text-muted-foreground">Update role details and permissions</p>
        </div>
      </div>
      <button
        @click="showDelete = true"
        class="h-10 px-4 rounded-lg bg-destructive text-destructive-foreground text-sm font-medium inline-flex items-center"
      >
        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
        Delete
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="h-8 w-8 animate-spin text-primary" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
    </div>

    <template v-else-if="role">
      <!-- Tabs -->
      <div class="border-b">
        <nav class="-mb-px flex gap-6">
          <button
            @click="activeTab = 'details'"
            :class="[
              'border-b-2 pb-3 text-sm font-medium transition-colors',
              activeTab === 'details'
                ? 'border-primary text-primary'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            ]"
          >
            Details
          </button>
          <button
            @click="activeTab = 'permissions'"
            :class="[
              'border-b-2 pb-3 text-sm font-medium transition-colors',
              activeTab === 'permissions'
                ? 'border-primary text-primary'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            ]"
          >
            Permissions
            <span class="ml-2 rounded-full bg-primary/10 px-2 py-0.5 text-xs">{{ selectedPerms.length }}</span>
          </button>
        </nav>
      </div>

      <!-- Details Tab -->
      <form v-if="activeTab === 'details'" @submit.prevent="saveDetails" class="rounded-xl border bg-card shadow-sm">
        <div class="border-b p-4">
          <h2 class="font-semibold">Role Details</h2>
        </div>
        <div class="space-y-4 p-4">
          <div class="space-y-2">
            <label class="text-sm font-medium">Name <span class="text-destructive">*</span></label>
            <input
              v-model="form.name"
              type="text"
              :class="['h-10 w-full rounded-lg border bg-background px-3 text-sm', errors.name ? 'border-destructive' : '']"
            />
            <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name[0] }}</p>
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium">Description</label>
            <textarea v-model="form.description" rows="2" class="w-full rounded-lg border bg-background px-3 py-2 text-sm" />
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium">Guard</label>
            <select v-model="form.guard_name" class="h-10 rounded-lg border bg-background px-3 text-sm">
              <option v-for="g in props.guards" :key="g" :value="g">{{ g }}</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4 pt-4 border-t">
            <div>
              <p class="text-sm text-muted-foreground">Created</p>
              <p class="text-sm font-medium">{{ formatDate(role.created_at) }}</p>
            </div>
            <div>
              <p class="text-sm text-muted-foreground">Updated</p>
              <p class="text-sm font-medium">{{ formatDate(role.updated_at) }}</p>
            </div>
            <div>
              <p class="text-sm text-muted-foreground">Permissions</p>
              <p class="text-sm font-medium">{{ selectedPerms.length }}</p>
            </div>
            <div>
              <p class="text-sm text-muted-foreground">Users</p>
              <p class="text-sm font-medium">{{ role.users_count || 0 }}</p>
            </div>
          </div>
        </div>
        <div class="flex justify-end border-t p-4">
          <button
            type="submit"
            :disabled="saving"
            class="h-10 px-4 rounded-lg bg-primary text-primary-foreground text-sm font-medium inline-flex items-center disabled:opacity-50"
          >
            <svg v-if="saving" class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ saving ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </form>

      <!-- Permissions Tab -->
      <div v-if="activeTab === 'permissions'" class="rounded-xl border bg-card shadow-sm">
        <div class="border-b p-4 flex items-center justify-between">
          <div>
            <h2 class="font-semibold">Manage Permissions</h2>
            <p class="text-sm text-muted-foreground">{{ selectedPerms.length }}/{{ allPerms.length }} assigned</p>
          </div>
        </div>
        <div class="p-4">
          <!-- Search -->
          <div class="relative mb-4">
            <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input v-model="permSearch" type="text" placeholder="Filter..." class="h-10 w-full rounded-lg border bg-background pl-10 pr-3 text-sm" />
          </div>

          <!-- Empty -->
          <div v-if="!Object.keys(groupedPerms).length" class="py-8 text-center text-muted-foreground">
            {{ permSearch ? 'No matches' : 'No permissions' }}
          </div>

          <!-- Groups -->
          <div v-else class="space-y-4">
            <div v-for="(perms, group) in groupedPerms" :key="group" class="rounded-lg border">
              <div class="flex items-center gap-3 bg-muted/50 px-4 py-3">
                <input
                  type="checkbox"
                  :checked="isGroupSelected(perms)"
                  :indeterminate="isGroupPartial(perms)"
                  @change="toggleGroup(perms)"
                  class="h-4 w-4 rounded"
                />
                <span class="font-medium capitalize">{{ group }}</span>
                <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">
                  {{ perms.filter(p => selectedPerms.includes(p.id)).length }}/{{ perms.length }}
                </span>
              </div>
              <div class="grid gap-2 p-4 sm:grid-cols-2 md:grid-cols-3">
                <label
                  v-for="p in perms"
                  :key="p.id"
                  :class="[
                    'flex items-center gap-2 rounded-lg border p-3 cursor-pointer transition-colors hover:bg-muted/50',
                    selectedPerms.includes(p.id) ? 'bg-primary/5 border-primary/50' : ''
                  ]"
                >
                  <input
                    type="checkbox"
                    :checked="selectedPerms.includes(p.id)"
                    @change="togglePerm(p.id)"
                    class="h-4 w-4 rounded"
                  />
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ p.label || p.name }}</p>
                  </div>
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>

  <ConfirmDialog
    v-model:open="showDelete"
    title="Delete Role"
    :description="`Delete '${role?.name}'?`"
    confirm-label="Delete"
    :loading="deleting"
    destructive
    @confirm="deleteRole"
  />
</template>

<script setup lang="ts">
import { ref } from 'vue'
import ConfirmDialog from '../../shared/ConfirmDialog.vue'

interface Role {
  id: number
  name: string
  guard_name: string
  permissions_count: number
  users_count: number
  created_at: string
  deleted_at: string | null
}

const props = defineProps<{
  roles: Role[]
  loading: boolean
  apiPrefix: string  // For API calls (e.g., admin/acl)
  uiPrefix: string   // For navigation (e.g., admin/acl/ui)
}>()

const emit = defineEmits<{ refresh: [] }>()

const showDelete = ref(false)
const roleToDelete = ref<Role | null>(null)
const deleting = ref(false)

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

const toast = (msg: string, type: 'success' | 'error' = 'success') => {
  const t = (window as any).__lr_toast
  if (t) type === 'success' ? t.success(msg) : t.error(msg)
}

const formatDate = (d: string) =>
  new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })

const confirmDelete = (role: Role) => {
  roleToDelete.value = role
  showDelete.value = true
}

const deleteRole = async () => {
  if (!roleToDelete.value) return
  deleting.value = true
  try {
    const res = await fetch(`/${props.apiPrefix}/roles/${roleToDelete.value.id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
    if (!res.ok) throw new Error()
    toast('Role deleted')
    emit('refresh')
  } catch {
    toast('Failed to delete', 'error')
  } finally {
    deleting.value = false
    showDelete.value = false
  }
}

const restoreRole = async (role: Role) => {
  try {
    const res = await fetch(`/${props.apiPrefix}/roles/${role.id}/restore`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
    if (!res.ok) throw new Error()
    toast('Role restored')
    emit('refresh')
  } catch {
    toast('Failed to restore', 'error')
  }
}
</script>

<template>
  <div class="rounded-xl border bg-card shadow-sm">
    <div class="flex items-center justify-between border-b p-4">
      <h3 class="font-semibold">Recent Roles</h3>
      <a :href="`/${uiPrefix}/roles`" class="text-sm font-medium text-primary hover:underline">
        View all →
      </a>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="p-6 space-y-3">
      <div v-for="i in 3" :key="i" class="flex items-center gap-4">
        <div class="h-10 w-10 animate-pulse rounded-full bg-muted" />
        <div class="flex-1 space-y-2">
          <div class="h-4 w-1/3 animate-pulse rounded bg-muted" />
          <div class="h-3 w-1/4 animate-pulse rounded bg-muted" />
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="roles.length === 0" class="p-8 text-center text-muted-foreground">
      <svg class="mx-auto h-12 w-12 mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
        />
      </svg>
      <p>No roles yet</p>
      <a :href="`/${uiPrefix}/roles/create`" class="mt-2 inline-block text-sm text-primary hover:underline">
        Create your first role →
      </a>
    </div>

    <!-- Roles list -->
    <div v-else class="divide-y">
      <div
        v-for="role in roles"
        :key="role.id"
        class="flex items-center justify-between p-4 hover:bg-muted/50 transition-colors"
        :class="{ 'bg-red-50/50 dark:bg-red-950/10': role.deleted_at }"
      >
        <div class="flex items-center gap-4">
          <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
            <svg class="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
              />
            </svg>
          </div>
          <div>
            <p class="font-medium">{{ role.name }}</p>
            <div class="flex items-center gap-2 text-xs text-muted-foreground">
              <span class="rounded border px-1.5 py-0.5">{{ role.guard_name }}</span>
              <span>{{ role.permissions_count }} perms</span>
              <span>•</span>
              <span>{{ formatDate(role.created_at) }}</span>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-1">
          <!-- Trashed: show restore button -->
          <template v-if="role.deleted_at">
            <button
              @click="restoreRole(role)"
              class="p-2 rounded-lg text-green-600 hover:bg-green-100 transition-colors"
              title="Restore"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
              </svg>
            </button>
          </template>

          <!-- Active: show edit/delete buttons -->
          <template v-else>
            <a
              :href="`/${uiPrefix}/roles/${role.id}/edit`"
              class="p-2 rounded-lg hover:bg-muted transition-colors"
              title="Edit"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                />
              </svg>
            </a>
            <button
              @click="confirmDelete(role)"
              class="p-2 rounded-lg text-destructive hover:bg-destructive/10 transition-colors"
              title="Delete"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                />
              </svg>
            </button>
          </template>
        </div>
      </div>
    </div>
  </div>

  <ConfirmDialog
    v-model:open="showDelete"
    title="Delete Role"
    :description="`Delete '${roleToDelete?.name}'? This can be undone.`"
    confirm-label="Delete"
    :loading="deleting"
    destructive
    @confirm="deleteRole"
  />
</template>

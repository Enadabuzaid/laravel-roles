<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import LaravelRolesLayout from '@/laravel-roles/layouts/LaravelRolesLayout.vue'
import LrPageHeader from '@/laravel-roles/components/LrPageHeader.vue'
import {
  LrButton,
  LrCard,
  LrCardHeader,
  LrCardTitle,
  LrCardContent,
  LrSwitch,
  LrBadge,
  LrSkeleton,
} from '@/laravel-roles/ui'

// Types
interface Role {
  id: number
  name: string
  guard_name: string
}

interface Permission {
  id: number
  name: string
  group?: string
}

interface MatrixData {
  roles: Role[]
  permissions: Permission[]
  matrix: Record<number, number[]> // roleId -> permissionIds
}

// State
const data = ref<MatrixData | null>(null)
const loading = ref(true)
const selectedRoleId = ref<number | null>(null)
const updating = ref<Set<string>>(new Set()) // Track updating permissions
const optimisticState = ref<Record<string, boolean>>({}) // Optimistic updates

// Get API URL
const getApiUrl = (path: string) => {
  const prefix = (window as any).laravelRoles?.apiPrefix || 'admin/acl'
  return `/${prefix}${path}`
}

// Fetch matrix data
const fetchMatrix = async () => {
  loading.value = true
  try {
    const response = await fetch(getApiUrl('/matrix'), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (!response.ok) throw new Error('Failed to fetch matrix')

    const result = await response.json()
    data.value = result.data || result

    // Select first role by default
    if (data.value?.roles.length && !selectedRoleId.value) {
      selectedRoleId.value = data.value.roles[0].id
    }
  } catch (error) {
    console.error('Error fetching matrix:', error)
  } finally {
    loading.value = false
  }
}

// Get selected role
const selectedRole = computed(() => {
  if (!data.value || !selectedRoleId.value) return null
  return data.value.roles.find(r => r.id === selectedRoleId.value)
})

// Group permissions by group
const groupedPermissions = computed(() => {
  if (!data.value) return {}
  const groups: Record<string, Permission[]> = {}
  for (const perm of data.value.permissions) {
    const group = perm.group || 'other'
    if (!groups[group]) groups[group] = []
    groups[group].push(perm)
  }
  return groups
})

// Check if role has permission
const hasPermission = (roleId: number, permissionId: number): boolean => {
  const key = `${roleId}-${permissionId}`
  
  // Check optimistic state first
  if (key in optimisticState.value) {
    return optimisticState.value[key]
  }
  
  // Check actual data
  if (!data.value) return false
  return data.value.matrix[roleId]?.includes(permissionId) || false
}

// Toggle single permission
const togglePermission = async (permissionId: number) => {
  if (!selectedRoleId.value || !data.value) return

  const roleId = selectedRoleId.value
  const key = `${roleId}-${permissionId}`
  const permission = data.value.permissions.find(p => p.id === permissionId)
  if (!permission) return

  // Mark as updating
  updating.value.add(key)

  // Optimistic update
  const currentValue = hasPermission(roleId, permissionId)
  optimisticState.value[key] = !currentValue

  try {
    const action = currentValue ? 'revoke' : 'grant'
    const response = await fetch(getApiUrl(`/roles/${roleId}/permissions/diff`), {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        [action]: [permission.name],
      }),
    })

    if (!response.ok) throw new Error('Failed to update permission')

    // Update actual data
    if (!data.value.matrix[roleId]) {
      data.value.matrix[roleId] = []
    }

    if (currentValue) {
      // Remove permission
      const idx = data.value.matrix[roleId].indexOf(permissionId)
      if (idx !== -1) data.value.matrix[roleId].splice(idx, 1)
    } else {
      // Add permission
      data.value.matrix[roleId].push(permissionId)
    }

    // Clear optimistic state
    delete optimisticState.value[key]
  } catch (error) {
    console.error('Error toggling permission:', error)
    
    // Rollback optimistic update
    delete optimisticState.value[key]
    
    const toast = (window as any).__lr_toast
    if (toast) toast.error('Failed to update permission')
  } finally {
    updating.value.delete(key)
  }
}

// Toggle all permissions in a group
const toggleGroup = async (groupPerms: Permission[]) => {
  if (!selectedRoleId.value || !data.value) return

  const roleId = selectedRoleId.value
  const permIds = groupPerms.map(p => p.id)
  const permNames = groupPerms.map(p => p.name)

  // Check if all are currently granted
  const allGranted = permIds.every(id => hasPermission(roleId, id))
  const action = allGranted ? 'revoke' : 'grant'

  // Optimistic update for all permissions
  for (const id of permIds) {
    const key = `${roleId}-${id}`
    updating.value.add(key)
    optimisticState.value[key] = !allGranted
  }

  try {
    const response = await fetch(getApiUrl(`/roles/${roleId}/permissions/diff`), {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        [action]: permNames,
      }),
    })

    if (!response.ok) throw new Error('Failed to update permissions')

    // Update actual data
    if (!data.value.matrix[roleId]) {
      data.value.matrix[roleId] = []
    }

    if (allGranted) {
      // Remove all
      data.value.matrix[roleId] = data.value.matrix[roleId].filter(id => !permIds.includes(id))
    } else {
      // Add all
      for (const id of permIds) {
        if (!data.value.matrix[roleId].includes(id)) {
          data.value.matrix[roleId].push(id)
        }
      }
    }

    // Clear optimistic state
    for (const id of permIds) {
      delete optimisticState.value[`${roleId}-${id}`]
    }

    const toast = (window as any).__lr_toast
    if (toast) toast.success(`Permissions ${action}ed successfully`)
  } catch (error) {
    console.error('Error toggling group:', error)
    
    // Rollback
    for (const id of permIds) {
      delete optimisticState.value[`${roleId}-${id}`]
    }
    
    const toast = (window as any).__lr_toast
    if (toast) toast.error('Failed to update permissions')
  } finally {
    for (const id of permIds) {
      updating.value.delete(`${roleId}-${id}`)
    }
  }
}

// Check if group has all permissions
const groupHasAll = (groupPerms: Permission[]): boolean => {
  if (!selectedRoleId.value) return false
  return groupPerms.every(p => hasPermission(selectedRoleId.value!, p.id))
}

// Check if group has some permissions
const groupHasSome = (groupPerms: Permission[]): boolean => {
  if (!selectedRoleId.value) return false
  const hasAny = groupPerms.some(p => hasPermission(selectedRoleId.value!, p.id))
  const hasAll = groupPerms.every(p => hasPermission(selectedRoleId.value!, p.id))
  return hasAny && !hasAll
}

// Breadcrumbs
const breadcrumbs = [
  { label: 'Dashboard', href: '/' },
  { label: 'Permission Matrix' },
]

// Initial fetch
onMounted(() => {
  fetchMatrix()
})
</script>

<template>
  <LaravelRolesLayout title="Permission Matrix" description="Manage role permissions">
    <div class="space-y-6">
      <!-- Page Header -->
      <LrPageHeader
        title="Permission Matrix"
        description="Toggle permissions for each role with a single click"
        :breadcrumbs="breadcrumbs"
      />

      <!-- Loading State -->
      <div v-if="loading" class="space-y-4">
        <LrSkeleton class="h-12 w-full" />
        <LrSkeleton class="h-64 w-full" />
      </div>

      <template v-else-if="data">
        <!-- Role Tabs -->
        <div class="border-b overflow-x-auto">
          <nav class="flex gap-1 min-w-max">
            <button
              v-for="role in data.roles"
              :key="role.id"
              type="button"
              :class="[
                'px-4 py-2 border-b-2 -mb-px text-sm font-medium whitespace-nowrap',
                selectedRoleId === role.id
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground'
              ]"
              @click="selectedRoleId = role.id"
            >
              {{ role.name }}
              <LrBadge variant="secondary" class="ml-2">
                {{ (data.matrix[role.id] || []).length }}
              </LrBadge>
            </button>
          </nav>
        </div>

        <!-- Permission Groups -->
        <div v-if="selectedRole" class="space-y-4">
          <div
            v-for="(perms, group) in groupedPermissions"
            :key="group"
            class="border rounded-lg overflow-hidden"
          >
            <!-- Group Header -->
            <div class="flex items-center justify-between p-4 bg-muted/50 border-b">
              <div class="flex items-center gap-3">
                <LrSwitch
                  :model-value="groupHasAll(perms)"
                  :class="{ 'opacity-60': groupHasSome(perms) && !groupHasAll(perms) }"
                  @update:model-value="toggleGroup(perms)"
                />
                <div>
                  <h3 class="font-semibold capitalize">{{ group }}</h3>
                  <p class="text-sm text-muted-foreground">
                    {{ perms.filter(p => hasPermission(selectedRoleId!, p.id)).length }} / {{ perms.length }} permissions
                  </p>
                </div>
              </div>
              <LrButton
                variant="ghost"
                size="sm"
                @click="toggleGroup(perms)"
              >
                {{ groupHasAll(perms) ? 'Revoke All' : 'Grant All' }}
              </LrButton>
            </div>

            <!-- Permissions List -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-px bg-border">
              <div
                v-for="perm in perms"
                :key="perm.id"
                class="flex items-center justify-between p-3 bg-background"
              >
                <span class="text-sm truncate" :title="perm.name">{{ perm.name }}</span>
                <LrSwitch
                  :model-value="hasPermission(selectedRoleId!, perm.id)"
                  :disabled="updating.has(`${selectedRoleId}-${perm.id}`)"
                  @update:model-value="togglePermission(perm.id)"
                />
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </LaravelRolesLayout>
</template>

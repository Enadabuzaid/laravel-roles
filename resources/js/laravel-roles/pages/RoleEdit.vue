<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import LaravelRolesLayout from '@/laravel-roles/layouts/LaravelRolesLayout.vue'
import LrPageHeader from '@/laravel-roles/components/LrPageHeader.vue'
import {
  LrButton,
  LrInput,
  LrCard,
  LrCardHeader,
  LrCardTitle,
  LrCardContent,
  LrCheckbox,
  LrSelect,
  LrBadge,
  LrDialog,
  LrSkeleton,
} from '@/laravel-roles/ui'

// Props from Inertia
const props = defineProps<{
  roleId?: number
}>()

// State
const role = ref<any>(null)
const name = ref('')
const description = ref('')
const guardName = ref('web')
const selectedPermissions = ref<number[]>([])
const allPermissions = ref<{ id: number; name: string; group: string }[]>([])
const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const showDeleteDialog = ref(false)
const deleting = ref(false)
const activeTab = ref<'details' | 'permissions'>('details')

// Get role ID from props or URL
const getRoleId = () => {
  if (props.roleId) return props.roleId
  const path = window.location.pathname
  const match = path.match(/\/roles\/(\d+)/)
  return match ? parseInt(match[1]) : null
}

// Get API/UI URLs
const getApiUrl = (path: string) => {
  const prefix = (window as any).laravelRoles?.apiPrefix || 'admin/acl'
  return `/${prefix}${path}`
}

const getUiUrl = (path: string) => {
  const prefix = (window as any).laravelRoles?.uiPrefix || 'admin/acl'
  return `/${prefix}${path}`
}

// Fetch role
const fetchRole = async () => {
  const id = getRoleId()
  if (!id) return

  loading.value = true
  try {
    const response = await fetch(getApiUrl(`/roles/${id}`), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (!response.ok) throw new Error('Failed to fetch role')

    const data = await response.json()
    role.value = data.data || data
    name.value = role.value.name
    description.value = role.value.description || ''
    guardName.value = role.value.guard_name

    // Fetch role's permissions
    const permsResponse = await fetch(getApiUrl(`/roles/${id}/permissions`), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (permsResponse.ok) {
      const permsData = await permsResponse.json()
      const rolePerms = permsData.data || permsData || []
      selectedPermissions.value = rolePerms.map((p: any) => p.id)
    }
  } catch (error) {
    console.error('Error fetching role:', error)
  } finally {
    loading.value = false
  }
}

// Fetch permissions
const fetchPermissions = async () => {
  try {
    const response = await fetch(getApiUrl('/permissions'), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (response.ok) {
      const data = await response.json()
      allPermissions.value = data.data || data || []
    }
  } catch (error) {
    console.error('Error fetching permissions:', error)
  }
}

// Update role
const updateRole = async () => {
  const id = getRoleId()
  if (!id) return

  saving.value = true
  errors.value = {}

  try {
    const response = await fetch(getApiUrl(`/roles/${id}`), {
      method: 'PUT',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        name: name.value,
        description: description.value,
        guard_name: guardName.value,
      }),
    })

    const data = await response.json()

    if (!response.ok) {
      if (response.status === 422) {
        errors.value = data.errors || {}
      }
      throw new Error(data.message || 'Failed to update role')
    }

    // Update permissions using diff
    await updatePermissions()

    const toast = (window as any).__lr_toast
    if (toast) toast.success('Role updated successfully')
  } catch (error) {
    console.error('Error updating role:', error)
    const toast = (window as any).__lr_toast
    if (toast) toast.error('Failed to update role')
  } finally {
    saving.value = false
  }
}

// Update permissions
const updatePermissions = async () => {
  const id = getRoleId()
  if (!id) return

  // Get permission names for the diff
  const permNames = selectedPermissions.value.map(permId => {
    const perm = allPermissions.value.find(p => p.id === permId)
    return perm?.name
  }).filter(Boolean)

  try {
    await fetch(getApiUrl(`/roles/${id}/permissions`), {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        permissions: permNames,
      }),
    })
  } catch (error) {
    console.error('Error updating permissions:', error)
  }
}

// Delete role
const deleteRole = async () => {
  const id = getRoleId()
  if (!id) return

  deleting.value = true
  try {
    const response = await fetch(getApiUrl(`/roles/${id}`), {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
    })

    if (!response.ok) throw new Error('Failed to delete role')

    const toast = (window as any).__lr_toast
    if (toast) toast.success('Role deleted successfully')

    window.location.href = getUiUrl('/roles')
  } catch (error) {
    console.error('Error deleting role:', error)
    const toast = (window as any).__lr_toast
    if (toast) toast.error('Failed to delete role')
  } finally {
    deleting.value = false
    showDeleteDialog.value = false
  }
}

// Group permissions
const groupedPermissions = computed(() => {
  const groups: Record<string, typeof allPermissions.value> = {}
  for (const perm of allPermissions.value) {
    const group = perm.group || 'other'
    if (!groups[group]) groups[group] = []
    groups[group].push(perm)
  }
  return groups
})

// Toggle permission
const togglePermission = (id: number) => {
  const index = selectedPermissions.value.indexOf(id)
  if (index === -1) {
    selectedPermissions.value.push(id)
  } else {
    selectedPermissions.value.splice(index, 1)
  }
}

// Toggle all in group
const toggleGroup = (groupPerms: typeof allPermissions.value) => {
  const groupIds = groupPerms.map(p => p.id)
  const allSelected = groupIds.every(id => selectedPermissions.value.includes(id))

  if (allSelected) {
    // Remove all
    selectedPermissions.value = selectedPermissions.value.filter(id => !groupIds.includes(id))
  } else {
    // Add all
    for (const id of groupIds) {
      if (!selectedPermissions.value.includes(id)) {
        selectedPermissions.value.push(id)
      }
    }
  }
}

// Guard options
const guardOptions = [
  { value: 'web', label: 'Web' },
  { value: 'api', label: 'API' },
]

// Breadcrumbs
const breadcrumbs = computed(() => [
  { label: 'Dashboard', href: '/' },
  { label: 'Roles', href: getUiUrl('/roles') },
  { label: role.value?.name || 'Edit' },
])

// Initial fetch
onMounted(() => {
  fetchRole()
  fetchPermissions()
})
</script>

<template>
  <LaravelRolesLayout title="Edit Role" description="Update role details and permissions">
    <div class="space-y-6">
      <!-- Loading State -->
      <div v-if="loading" class="space-y-6">
        <LrSkeleton class="h-24 w-full" />
        <LrSkeleton class="h-64 w-full" />
      </div>

      <template v-else-if="role">
        <!-- Page Header -->
        <LrPageHeader
          :title="`Edit: ${role.name}`"
          description="Update role details and manage permissions"
          :breadcrumbs="breadcrumbs"
        >
          <template #actions>
            <LrButton variant="outline" @click="showDeleteDialog = true">
              Delete
            </LrButton>
          </template>
        </LrPageHeader>

        <!-- Tabs -->
        <div class="border-b">
          <nav class="flex gap-4">
            <button
              type="button"
              :class="[
                'py-2 px-1 border-b-2 -mb-px text-sm font-medium',
                activeTab === 'details'
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground'
              ]"
              @click="activeTab = 'details'"
            >
              Details
            </button>
            <button
              type="button"
              :class="[
                'py-2 px-1 border-b-2 -mb-px text-sm font-medium',
                activeTab === 'permissions'
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground'
              ]"
              @click="activeTab = 'permissions'"
            >
              Permissions
              <LrBadge variant="secondary" class="ml-2">
                {{ selectedPermissions.length }}
              </LrBadge>
            </button>
          </nav>
        </div>

        <form @submit.prevent="updateRole" class="space-y-6">
          <!-- Details Tab -->
          <LrCard v-show="activeTab === 'details'">
            <LrCardHeader>
              <LrCardTitle class="text-lg">Basic Information</LrCardTitle>
            </LrCardHeader>
            <LrCardContent class="space-y-4">
              <!-- Name -->
              <div class="space-y-2">
                <label class="text-sm font-medium" for="name">Name *</label>
                <LrInput
                  id="name"
                  v-model="name"
                  placeholder="Enter role name"
                  :class="{ 'border-red-500': errors.name }"
                />
                <p v-if="errors.name" class="text-sm text-red-500">{{ errors.name[0] }}</p>
              </div>

              <!-- Description -->
              <div class="space-y-2">
                <label class="text-sm font-medium" for="description">Description</label>
                <textarea
                  id="description"
                  v-model="description"
                  placeholder="Describe what this role can do"
                  rows="3"
                  class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm
                    ring-offset-background placeholder:text-muted-foreground
                    focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                />
              </div>

              <!-- Guard -->
              <div class="space-y-2">
                <label class="text-sm font-medium">Guard</label>
                <LrSelect
                  v-model="guardName"
                  :options="guardOptions"
                  class="w-full sm:w-48"
                />
              </div>
            </LrCardContent>
          </LrCard>

          <!-- Permissions Tab -->
          <LrCard v-show="activeTab === 'permissions'">
            <LrCardHeader>
              <LrCardTitle class="text-lg">Assigned Permissions</LrCardTitle>
            </LrCardHeader>
            <LrCardContent>
              <div v-if="allPermissions.length === 0" class="text-center py-8 text-muted-foreground">
                No permissions available.
              </div>

              <div v-else class="space-y-6">
                <div v-for="(perms, group) in groupedPermissions" :key="group" class="space-y-3">
                  <div class="flex items-center justify-between">
                    <h4 class="font-medium capitalize">{{ group }}</h4>
                    <LrButton
                      type="button"
                      variant="ghost"
                      size="sm"
                      @click="toggleGroup(perms)"
                    >
                      Toggle All
                    </LrButton>
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    <label
                      v-for="perm in perms"
                      :key="perm.id"
                      class="flex items-center gap-2 p-3 rounded-md border hover:bg-muted/50 cursor-pointer"
                      :class="{ 'bg-muted/50 border-primary': selectedPermissions.includes(perm.id) }"
                    >
                      <LrCheckbox
                        :model-value="selectedPermissions.includes(perm.id)"
                        @update:model-value="togglePermission(perm.id)"
                      />
                      <span class="text-sm">{{ perm.name }}</span>
                    </label>
                  </div>
                </div>
              </div>
            </LrCardContent>
          </LrCard>

          <!-- Actions -->
          <div class="flex justify-end gap-4">
            <LrButton type="button" variant="outline" @click="window.location.href = getUiUrl('/roles')">
              Cancel
            </LrButton>
            <LrButton type="submit" :loading="saving">
              Save Changes
            </LrButton>
          </div>
        </form>
      </template>
    </div>

    <!-- Delete Dialog -->
    <LrDialog
      v-model:open="showDeleteDialog"
      title="Delete Role"
      :description="`Are you sure you want to delete '${role?.name}'? This action cannot be undone.`"
      confirm-label="Delete"
      :loading="deleting"
      destructive
      @confirm="deleteRole"
    />
  </LaravelRolesLayout>
</template>

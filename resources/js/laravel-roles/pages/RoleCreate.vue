<script setup lang="ts">
import { ref, onMounted } from 'vue'
import LaravelRolesLayout from '../layouts/LaravelRolesLayout.vue'
import LrPageHeader from '../components/LrPageHeader.vue'
import {
  LrButton,
  LrInput,
  LrCard,
  LrCardHeader,
  LrCardTitle,
  LrCardContent,
  LrCheckbox,
  LrSelect,
} from '../ui'

// State
const name = ref('')
const description = ref('')
const guardName = ref('web')
const selectedPermissions = ref<number[]>([])
const permissions = ref<{ id: number; name: string; group: string }[]>([])
const loading = ref(false)
const errors = ref<Record<string, string[]>>({})

// Get API prefix from config
const getApiUrl = (path: string) => {
  const prefix = (window as any).laravelRoles?.apiPrefix || 'admin/acl'
  return `/${prefix}${path}`
}

const getUiUrl = (path: string) => {
  const prefix = (window as any).laravelRoles?.uiPrefix || 'admin/acl'
  return `/${prefix}${path}`
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
      permissions.value = data.data || data || []
    }
  } catch (error) {
    console.error('Error fetching permissions:', error)
  }
}

// Create role
const createRole = async () => {
  loading.value = true
  errors.value = {}

  try {
    const response = await fetch(getApiUrl('/roles'), {
      method: 'POST',
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
        permissions: selectedPermissions.value,
      }),
    })

    const data = await response.json()

    if (!response.ok) {
      if (response.status === 422) {
        errors.value = data.errors || {}
      }
      throw new Error(data.message || 'Failed to create role')
    }

    // Show success toast
    const toast = (window as any).__lr_toast
    if (toast) toast.success('Role created successfully')

    // Navigate to roles list
    window.location.href = getUiUrl('/roles')
  } catch (error) {
    console.error('Error creating role:', error)
    const toast = (window as any).__lr_toast
    if (toast) toast.error('Failed to create role')
  } finally {
    loading.value = false
  }
}

// Group permissions by group
const groupedPermissions = () => {
  const groups: Record<string, typeof permissions.value> = {}
  for (const perm of permissions.value) {
    const group = perm.group || 'other'
    if (!groups[group]) groups[group] = []
    groups[group].push(perm)
  }
  return groups
}

// Toggle permission
const togglePermission = (id: number) => {
  const index = selectedPermissions.value.indexOf(id)
  if (index === -1) {
    selectedPermissions.value.push(id)
  } else {
    selectedPermissions.value.splice(index, 1)
  }
}

// Guard options
const guardOptions = [
  { value: 'web', label: 'Web' },
  { value: 'api', label: 'API' },
]

// Breadcrumbs
const breadcrumbs = [
  { label: 'Dashboard', href: '/' },
  { label: 'Roles', href: getUiUrl('/roles') },
  { label: 'Create' },
]

// Initial fetch
onMounted(() => {
  fetchPermissions()
})
</script>

<template>
  <LaravelRolesLayout title="Create Role" description="Create a new role">
    <div class="space-y-6">
      <!-- Page Header -->
      <LrPageHeader
        title="Create Role"
        description="Create a new role and assign permissions"
        :breadcrumbs="breadcrumbs"
      >
        <template #actions>
          <LrButton variant="outline" @click="window.location.href = getUiUrl('/roles')">
            Cancel
          </LrButton>
        </template>
      </LrPageHeader>

      <form @submit.prevent="createRole" class="space-y-6">
        <!-- Basic Info -->
        <LrCard>
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
                placeholder="Enter role name (e.g., editor)"
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
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2
                  disabled:cursor-not-allowed disabled:opacity-50"
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

        <!-- Permissions -->
        <LrCard>
          <LrCardHeader>
            <LrCardTitle class="text-lg">Permissions</LrCardTitle>
          </LrCardHeader>
          <LrCardContent>
            <div v-if="permissions.length === 0" class="text-center py-8 text-muted-foreground">
              No permissions available. Run `php artisan roles:sync` to seed permissions.
            </div>

            <div v-else class="space-y-6">
              <div v-for="(perms, group) in groupedPermissions()" :key="group" class="space-y-3">
                <h4 class="font-medium capitalize">{{ group }}</h4>
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
          <LrButton type="submit" :loading="loading">
            Create Role
          </LrButton>
        </div>
      </form>
    </div>
  </LaravelRolesLayout>
</template>

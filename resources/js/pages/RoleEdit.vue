<script setup lang="ts">
/**
 * RoleEdit Page
 *
 * Edit role with permission management.
 */
import { ref, onMounted, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  ArrowLeftIcon,
  SaveIcon,
  Trash2Icon,
  Loader2,
  ShieldIcon,
  KeyIcon,
} from 'lucide-vue-next'

// Laravel Roles custom components
import PageHeader from '@/laravel-roles/components/ui/PageHeader.vue'
import ConfirmDialog from '@/laravel-roles/components/ui/ConfirmDialog.vue'

// Laravel Roles API layer
import { useRolesApi } from '@/laravel-roles/composables/useRolesApi'
import { uiUrl } from '@/laravel-roles/api/config'
import type { Role } from '@/laravel-roles/types'

// Props from Inertia
const props = defineProps<{
  roleId: number
  guards?: string[]
}>()

// Composable
const {
  currentRole,
  isLoading,
  isSaving,
  fetchRole,
  updateRole,
  deleteRole,
} = useRolesApi()

// Form data
const form = ref({
  name: '',
  label: '',
  description: '',
  guard_name: 'web',
})

// Validation errors
const errors = ref<Record<string, string>>({})

// Delete dialog
const deleteDialog = ref({
  open: false,
})

// Available guards
const availableGuards = computed(() => props.guards || ['web', 'api'])

// Load role data
onMounted(async () => {
  const role = await fetchRole(props.roleId)
  if (role) {
    form.value = {
      name: role.name,
      label: typeof role.label === 'string' ? role.label : role.label?.en || '',
      description: typeof role.description === 'string' ? role.description : role.description?.en || '',
      guard_name: role.guard_name,
    }
  }
})

// Form validation
const validate = (): boolean => {
  errors.value = {}

  if (!form.value.name.trim()) {
    errors.value.name = 'Name is required'
  } else if (!/^[a-z0-9_-]+$/.test(form.value.name)) {
    errors.value.name = 'Name can only contain lowercase letters, numbers, underscores, and hyphens'
  }

  return Object.keys(errors.value).length === 0
}

// Submit handler
const handleSubmit = async () => {
  if (!validate()) return

  const role = await updateRole(props.roleId, {
    name: form.value.name,
    label: form.value.label || undefined,
    description: form.value.description || undefined,
    guard_name: form.value.guard_name,
  })

  // Stay on page after update
}

// Delete handler
const handleDelete = async () => {
  const success = await deleteRole(props.roleId)
  if (success) {
    router.visit(uiUrl('/roles'))
  }
  deleteDialog.value.open = false
}

// Navigation
const navigateBack = () => {
  router.visit(uiUrl('/roles'))
}

const navigateToMatrix = () => {
  router.visit(uiUrl('/matrix'))
}

// Breadcrumbs
const breadcrumbs = computed(() => [
  { label: 'Access Control' },
  { label: 'Roles', href: uiUrl('/roles') },
  { label: currentRole.value?.name || 'Edit' },
])
</script>

<template>
  <div class="container mx-auto py-6 space-y-6">
    <Head :title="`Edit ${currentRole?.name || 'Role'}`" />

    <!-- Page Header -->
    <PageHeader
      :title="`Edit Role: ${currentRole?.name || ''}`"
      description="Modify role details and permissions"
      :breadcrumbs="breadcrumbs"
    >
      <template #actions>
        <Button variant="outline" @click="navigateBack">
          <ArrowLeftIcon class="mr-2 h-4 w-4" />
          Back
        </Button>
      </template>
    </PageHeader>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-12">
      <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
    </div>

    <!-- Content -->
    <div v-else-if="currentRole" class="space-y-6">
      <Tabs default-value="details" class="space-y-6">
        <TabsList>
          <TabsTrigger value="details">
            <ShieldIcon class="mr-2 h-4 w-4" />
            Details
          </TabsTrigger>
          <TabsTrigger value="permissions">
            <KeyIcon class="mr-2 h-4 w-4" />
            Permissions
          </TabsTrigger>
        </TabsList>

        <!-- Details Tab -->
        <TabsContent value="details">
          <Card class="max-w-2xl">
            <CardHeader>
              <CardTitle>Role Details</CardTitle>
              <CardDescription>
                Update the role information.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form @submit.prevent="handleSubmit" class="space-y-6">
                <!-- Name -->
                <div class="space-y-2">
                  <Label for="name">Name <span class="text-destructive">*</span></Label>
                  <Input
                    id="name"
                    v-model="form.name"
                    placeholder="e.g., editor, moderator"
                    :class="{ 'border-destructive': errors.name }"
                  />
                  <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name }}</p>
                </div>

                <!-- Label -->
                <div class="space-y-2">
                  <Label for="label">Display Label</Label>
                  <Input
                    id="label"
                    v-model="form.label"
                    placeholder="e.g., Content Editor"
                  />
                </div>

                <!-- Description -->
                <div class="space-y-2">
                  <Label for="description">Description</Label>
                  <Textarea
                    id="description"
                    v-model="form.description"
                    placeholder="Describe what this role can do..."
                    rows="3"
                  />
                </div>

                <!-- Guard -->
                <div class="space-y-2">
                  <Label for="guard">Guard</Label>
                  <Select v-model="form.guard_name">
                    <SelectTrigger>
                      <SelectValue placeholder="Select guard" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="guard in availableGuards" :key="guard" :value="guard">
                        {{ guard }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <!-- Actions -->
                <div class="flex gap-4 pt-4">
                  <Button type="submit" :disabled="isSaving">
                    <Loader2 v-if="isSaving" class="mr-2 h-4 w-4 animate-spin" />
                    <SaveIcon v-else class="mr-2 h-4 w-4" />
                    Save Changes
                  </Button>
                </div>
              </form>

              <Separator class="my-6" />

              <!-- Danger Zone -->
              <div class="space-y-4">
                <h3 class="text-lg font-medium text-destructive">Danger Zone</h3>
                <p class="text-sm text-muted-foreground">
                  Deleting this role will remove it from all users. This action can be undone.
                </p>
                <Button
                  variant="destructive"
                  @click="deleteDialog.open = true"
                >
                  <Trash2Icon class="mr-2 h-4 w-4" />
                  Delete Role
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- Permissions Tab -->
        <TabsContent value="permissions">
          <Card>
            <CardHeader>
              <CardTitle>Permissions</CardTitle>
              <CardDescription>
                Manage permissions for this role.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <!-- Current Permissions Summary -->
              <div class="space-y-4">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-medium">Current Permissions:</span>
                  <Badge variant="secondary">
                    {{ currentRole.permissions_count || 0 }} assigned
                  </Badge>
                </div>

                <!-- Permission List -->
                <div v-if="currentRole.permissions?.length" class="flex flex-wrap gap-2">
                  <Badge
                    v-for="permission in currentRole.permissions"
                    :key="permission.id"
                    variant="outline"
                  >
                    {{ permission.name }}
                  </Badge>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                  No permissions assigned yet.
                </p>

                <Separator />

                <div class="flex justify-center">
                  <Button variant="outline" @click="navigateToMatrix">
                    <KeyIcon class="mr-2 h-4 w-4" />
                    Manage Permissions in Matrix
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>

    <!-- Delete Confirmation Dialog -->
    <ConfirmDialog
      v-model:open="deleteDialog.open"
      title="Delete Role"
      :description="`Are you sure you want to delete '${currentRole?.name}'? This action can be undone.`"
      confirm-label="Delete"
      variant="destructive"
      :loading="isSaving"
      @confirm="handleDelete"
    />
  </div>
</template>

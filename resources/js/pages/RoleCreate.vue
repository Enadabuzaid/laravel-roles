<script setup lang="ts">
/**
 * RoleCreate Page
 *
 * Create new role form.
 */
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { ArrowLeftIcon, SaveIcon, Loader2 } from 'lucide-vue-next'

import PageHeader from '@/components/ui/PageHeader.vue'
import { useRolesApi } from '@/composables/useRolesApi'
import { uiUrl } from '@/api/config'

// Props
const props = defineProps<{
  guards?: string[]
  permissions?: Array<{ id: number; name: string; group?: string }>
}>()

// Composable
const { createRole, isSaving } = useRolesApi()

// Form data
const form = ref({
  name: '',
  label: '',
  description: '',
  guard_name: 'web',
  permission_ids: [] as number[],
})

// Validation errors
const errors = ref<Record<string, string>>({})

// Available guards
const availableGuards = computed(() => props.guards || ['web', 'api'])

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

  const role = await createRole({
    name: form.value.name,
    label: form.value.label || undefined,
    description: form.value.description || undefined,
    guard_name: form.value.guard_name,
    permission_ids: form.value.permission_ids.length > 0 ? form.value.permission_ids : undefined,
  })

  if (role) {
    router.visit(uiUrl('/roles'))
  }
}

// Navigation
const navigateBack = () => {
  router.visit(uiUrl('/roles'))
}

// Breadcrumbs
const breadcrumbs = [
  { label: 'Access Control' },
  { label: 'Roles', href: uiUrl('/roles') },
  { label: 'Create' },
]
</script>

<template>
  <div class="container mx-auto py-6 space-y-6">
    <Head title="Create Role" />

    <!-- Page Header -->
    <PageHeader
      title="Create Role"
      description="Add a new role to the system"
      :breadcrumbs="breadcrumbs"
    >
      <template #actions>
        <Button variant="outline" @click="navigateBack">
          <ArrowLeftIcon class="mr-2 h-4 w-4" />
          Back
        </Button>
      </template>
    </PageHeader>

    <!-- Form Card -->
    <Card class="max-w-2xl">
      <CardHeader>
        <CardTitle>Role Details</CardTitle>
        <CardDescription>
          Enter the details for the new role.
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
            <p class="text-sm text-muted-foreground">
              Lowercase letters, numbers, underscores, and hyphens only.
            </p>
          </div>

          <!-- Label -->
          <div class="space-y-2">
            <Label for="label">Display Label</Label>
            <Input
              id="label"
              v-model="form.label"
              placeholder="e.g., Content Editor"
            />
            <p class="text-sm text-muted-foreground">
              Human-readable label for the role.
            </p>
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
              Create Role
            </Button>
            <Button type="button" variant="outline" @click="navigateBack">
              Cancel
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>

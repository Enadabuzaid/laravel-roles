<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/components/ui/accordion'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import type { Role, PermissionGroup } from '@/types'

interface Props {
  role?: Role
  permissionGroups: PermissionGroup[]
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false
})

const emit = defineEmits<{
  submit: [payload: {
    name: string
    display_name?: string
    description?: string
    guard_name: string
    permission_ids: number[]
  }]
  cancel: []
}>()

// Form data
const formData = ref({
  name: props.role?.name || '',
  display_name: props.role?.display_name || '',
  description: props.role?.description || '',
  guard_name: props.role?.guard_name || 'web'
})

const selectedPermissions = ref<Set<number>>(
  new Set(props.role?.permissions?.map(p => p.id) || [])
)

// Guard options
const guards = [
  { value: 'web', label: 'Web' },
  { value: 'api', label: 'API' },
  { value: 'admin', label: 'Admin' }
]

// Computed
const isEditing = computed(() => Boolean(props.role))

const selectedCount = computed(() => selectedPermissions.value.size)

const groupStats = computed(() => {
  const stats: Record<string, { selected: number; total: number }> = {}

  props.permissionGroups.forEach(group => {
    const selected = group.permissions.filter(p =>
      selectedPermissions.value.has(p.id)
    ).length

    stats[group.name] = {
      selected,
      total: group.permissions.length
    }
  })

  return stats
})

// Methods
const togglePermission = (permissionId: number, checked: boolean) => {
  if (checked) {
    selectedPermissions.value.add(permissionId)
  } else {
    selectedPermissions.value.delete(permissionId)
  }
}

const toggleGroup = (group: PermissionGroup, checked: boolean) => {
  group.permissions.forEach(permission => {
    togglePermission(permission.id, checked)
  })
}

const isGroupFullySelected = (group: PermissionGroup): boolean => {
  return group.permissions.every(p => selectedPermissions.value.has(p.id))
}

const isGroupPartiallySelected = (group: PermissionGroup): boolean => {
  const selectedInGroup = group.permissions.filter(p =>
    selectedPermissions.value.has(p.id)
  ).length
  return selectedInGroup > 0 && selectedInGroup < group.permissions.length
}

const handleSubmit = () => {
  emit('submit', {
    name: formData.value.name,
    display_name: formData.value.display_name || undefined,
    description: formData.value.description || undefined,
    guard_name: formData.value.guard_name,
    permission_ids: Array.from(selectedPermissions.value)
  })
}

const selectAll = () => {
  props.permissionGroups.forEach(group => {
    group.permissions.forEach(permission => {
      selectedPermissions.value.add(permission.id)
    })
  })
}

const deselectAll = () => {
  selectedPermissions.value.clear()
}

// Watch for role changes (when editing)
watch(() => props.role, (newRole) => {
  if (newRole) {
    formData.value = {
      name: newRole.name || '',
      display_name: newRole.display_name || '',
      description: newRole.description || '',
      guard_name: newRole.guard_name || 'web'
    }
    selectedPermissions.value = new Set(newRole.permissions?.map(p => p.id) || [])
  }
}, { immediate: true })
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <!-- Basic Information Card -->
    <Card>
      <CardContent class="pt-6 space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
          <!-- Name -->
          <div class="space-y-2">
            <Label for="name" class="required">
              {{ $t('roles.fields.name') }}
            </Label>
            <Input
              id="name"
              v-model="formData.name"
              required
              :placeholder="$t('roles.placeholders.name')"
              :disabled="loading"
            />
            <p class="text-xs text-muted-foreground">
              {{ $t('roles.hints.name') }}
            </p>
          </div>

          <!-- Display Name -->
          <div class="space-y-2">
            <Label for="display_name">
              {{ $t('roles.fields.display_name') }}
            </Label>
            <Input
              id="display_name"
              v-model="formData.display_name"
              :placeholder="$t('roles.placeholders.display_name')"
              :disabled="loading"
            />
          </div>
        </div>

        <!-- Guard -->
        <div class="space-y-2">
          <Label for="guard_name" class="required">
            {{ $t('roles.fields.guard') }}
          </Label>
          <Select v-model="formData.guard_name" :disabled="isEditing || loading">
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="guard in guards"
                :key="guard.value"
                :value="guard.value"
              >
                {{ guard.label }}
              </SelectItem>
            </SelectContent>
          </Select>
          <p v-if="isEditing" class="text-xs text-muted-foreground">
            {{ $t('roles.hints.guard_readonly') }}
          </p>
        </div>

        <!-- Description -->
        <div class="space-y-2">
          <Label for="description">
            {{ $t('roles.fields.description') }}
          </Label>
          <Textarea
            id="description"
            v-model="formData.description"
            :placeholder="$t('roles.placeholders.description')"
            rows="3"
            :disabled="loading"
          />
        </div>
      </CardContent>
    </Card>

    <!-- Permissions Section -->
    <Card>
      <CardContent class="pt-6 space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-semibold">
              {{ $t('roles.permissions.title') }}
            </h3>
            <p class="text-sm text-muted-foreground">
              {{ $t('roles.permissions.subtitle', { count: selectedCount }) }}
            </p>
          </div>
          <div class="flex gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              @click="selectAll"
              :disabled="loading"
            >
              {{ $t('roles.permissions.select_all') }}
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              @click="deselectAll"
              :disabled="loading"
            >
              {{ $t('roles.permissions.deselect_all') }}
            </Button>
          </div>
        </div>

        <!-- Permissions by Group -->
        <Accordion type="multiple" class="w-full">
          <AccordionItem
            v-for="group in permissionGroups"
            :key="group.name"
            :value="group.name"
          >
            <AccordionTrigger class="hover:no-underline">
              <div class="flex items-center justify-between flex-1 pr-4">
                <div class="flex items-center gap-3">
                  <Checkbox
                    :checked="isGroupFullySelected(group)"
                    :indeterminate="isGroupPartiallySelected(group)"
                    @update:checked="toggleGroup(group, $event as boolean)"
                    @click.stop
                    :disabled="loading"
                  />
                  <span class="font-medium">
                    {{ group.label || group.name }}
                  </span>
                </div>
                <Badge variant="secondary" class="ml-auto">
                  {{ groupStats[group.name]?.selected || 0 }} / {{ group.permissions.length }}
                </Badge>
              </div>
            </AccordionTrigger>
            <AccordionContent>
              <div class="grid gap-3 pt-4 pl-9 md:grid-cols-2">
                <div
                  v-for="permission in group.permissions"
                  :key="permission.id"
                  class="flex items-start space-x-3 p-2 rounded-md hover:bg-muted/50 transition-colors"
                >
                  <Checkbox
                    :id="`permission-${permission.id}`"
                    :checked="selectedPermissions.has(permission.id)"
                    @update:checked="togglePermission(permission.id, $event as boolean)"
                    :disabled="loading"
                  />
                  <label
                    :for="`permission-${permission.id}`"
                    class="flex-1 cursor-pointer"
                  >
                    <div class="font-medium text-sm">
                      {{ permission.display_name || permission.name }}
                    </div>
                    <div v-if="permission.description" class="text-xs text-muted-foreground mt-0.5">
                      {{ permission.description }}
                    </div>
                  </label>
                </div>
              </div>
            </AccordionContent>
          </AccordionItem>
        </Accordion>
      </CardContent>
    </Card>

    <!-- Form Actions -->
    <div class="flex items-center justify-end gap-3">
      <Button
        type="button"
        variant="outline"
        @click="emit('cancel')"
        :disabled="loading"
      >
        {{ $t('common.cancel') }}
      </Button>
      <Button
        type="submit"
        :disabled="loading || !formData.name"
      >
        <span v-if="loading" class="mr-2">
          <div class="h-4 w-4 border-2 border-current border-t-transparent rounded-full animate-spin" />
        </span>
        {{ isEditing ? $t('common.update') : $t('common.create') }}
      </Button>
    </div>
  </form>
</template>

<style scoped>
.required::after {
  content: " *";
  color: rgb(239 68 68);
}
</style>


<script setup lang="ts">
/**
 * PermissionToggleRow Component
 *
 * Single permission row with toggle switch for a role.
 * Handles optimistic updates with rollback on failure.
 */
import { ref, computed } from 'vue'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import { Loader2 } from 'lucide-vue-next'
import type { MatrixPermission } from '@/types'

const props = defineProps<{
  permission: MatrixPermission
  roleId: number
  hasPermission: boolean
  disabled?: boolean
}>()

const emit = defineEmits<{
  (e: 'toggle', payload: { permissionId: number; permissionName: string; roleId: number; value: boolean }): void
}>()

const isLoading = ref(false)
const optimisticValue = ref(props.hasPermission)

// Track if value has changed from props
const localValue = computed({
  get: () => optimisticValue.value,
  set: (value) => {
    optimisticValue.value = value
  }
})

const handleToggle = (checked: boolean) => {
  // Optimistic update
  const previousValue = optimisticValue.value
  optimisticValue.value = checked
  isLoading.value = true

  emit('toggle', {
    permissionId: props.permission.id,
    permissionName: props.permission.name,
    roleId: props.roleId,
    value: checked,
  })

  // Parent will handle success/failure and may call rollback
}

// Expose rollback method for parent
const rollback = (value: boolean) => {
  optimisticValue.value = value
  isLoading.value = false
}

const setLoading = (loading: boolean) => {
  isLoading.value = loading
}

defineExpose({ rollback, setLoading })
</script>

<template>
  <div class="flex items-center justify-between py-2 px-3 hover:bg-muted/50 rounded-md transition-colors">
    <div class="flex-1 min-w-0">
      <Label class="font-normal cursor-pointer">
        {{ permission.label || permission.name }}
      </Label>
    </div>

    <div class="flex items-center gap-2">
      <Loader2 v-if="isLoading" class="h-4 w-4 animate-spin text-muted-foreground" />
      <Switch
        :checked="localValue"
        @update:checked="handleToggle"
        :disabled="disabled || isLoading"
        class="data-[state=checked]:bg-primary"
      />
    </div>
  </div>
</template>

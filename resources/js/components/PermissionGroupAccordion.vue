<script setup lang="ts">
/**
 * PermissionGroupAccordion Component
 *
 * Collapsible group of permissions with group-level toggle.
 */
import { ref, computed } from 'vue'
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/components/ui/accordion'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Loader2, ChevronDown, CheckCircle2, XCircle } from 'lucide-vue-next'
import PermissionToggleRow from './PermissionToggleRow.vue'
import type { MatrixPermission } from '@/types'

export interface GroupPermission extends MatrixPermission {
  hasPermission: boolean
}

const props = defineProps<{
  groupName: string
  groupLabel?: string
  permissions: GroupPermission[]
  roleId: number
  disabled?: boolean
}>()

const emit = defineEmits<{
  (e: 'toggle', payload: { permissionId: number; permissionName: string; roleId: number; value: boolean }): void
  (e: 'toggleGroup', payload: { groupName: string; permissionNames: string[]; roleId: number; value: boolean }): void
}>()

const isGroupLoading = ref(false)

// Count of assigned permissions in this group
const assignedCount = computed(() => {
  return props.permissions.filter(p => p.hasPermission).length
})

// Are all permissions in group assigned?
const allAssigned = computed(() => {
  return assignedCount.value === props.permissions.length
})

// Are some permissions assigned?
const someAssigned = computed(() => {
  return assignedCount.value > 0 && assignedCount.value < props.permissions.length
})

// Toggle all permissions in group
const handleGroupToggle = (checked: boolean) => {
  isGroupLoading.value = true

  const permissionNames = props.permissions.map(p => p.name)

  emit('toggleGroup', {
    groupName: props.groupName,
    permissionNames,
    roleId: props.roleId,
    value: checked,
  })
}

const setGroupLoading = (loading: boolean) => {
  isGroupLoading.value = loading
}

defineExpose({ setGroupLoading })
</script>

<template>
  <AccordionItem :value="groupName" class="border rounded-lg px-4">
    <div class="flex items-center gap-3">
      <!-- Group Toggle Checkbox -->
      <div class="flex items-center" @click.stop>
        <Loader2 v-if="isGroupLoading" class="h-4 w-4 animate-spin text-muted-foreground" />
        <Checkbox
          v-else
          :checked="allAssigned"
          :indeterminate="someAssigned"
          @update:checked="handleGroupToggle"
          :disabled="disabled"
        />
      </div>

      <AccordionTrigger class="flex-1 hover:no-underline">
        <div class="flex items-center gap-3">
          <span class="font-medium">{{ groupLabel || groupName }}</span>
          <Badge variant="secondary" class="text-xs">
            {{ assignedCount }} / {{ permissions.length }}
          </Badge>

          <!-- Status indicator -->
          <CheckCircle2 v-if="allAssigned" class="h-4 w-4 text-green-500" />
          <XCircle v-else-if="assignedCount === 0" class="h-4 w-4 text-muted-foreground" />
        </div>
      </AccordionTrigger>
    </div>

    <AccordionContent>
      <div class="space-y-1 pl-7 pb-2">
        <PermissionToggleRow
          v-for="permission in permissions"
          :key="permission.id"
          :permission="permission"
          :role-id="roleId"
          :has-permission="permission.hasPermission"
          :disabled="disabled"
          @toggle="emit('toggle', $event)"
        />
      </div>
    </AccordionContent>
  </AccordionItem>
</template>

<script setup lang="ts">
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group'
import { TableIcon, LayoutGridIcon } from 'lucide-vue-next'
import type { ViewMode } from '@/types'

defineProps<{
  modelValue: ViewMode
}>()

const emit = defineEmits<{
  'update:modelValue': [value: ViewMode]
}>()
</script>

<template>
  <ToggleGroup
    type="single"
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event as ViewMode)"
    class="border rounded-md"
  >
    <ToggleGroupItem value="table" aria-label="Table view" class="gap-2">
      <TableIcon class="h-4 w-4" />
      <span class="sr-only md:not-sr-only">{{ $t('common.table') }}</span>
    </ToggleGroupItem>
    <ToggleGroupItem value="grid" aria-label="Grid view" class="gap-2">
      <LayoutGridIcon class="h-4 w-4" />
      <span class="sr-only md:not-sr-only">{{ $t('common.grid') }}</span>
    </ToggleGroupItem>
  </ToggleGroup>
</template>


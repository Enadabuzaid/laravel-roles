<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { TableIcon, LayoutGridIcon } from 'lucide-vue-next'
import type { ViewMode } from '@/laravel-roles/types'

defineProps<{
  modelValue: ViewMode
}>()

const emit = defineEmits<{
  'update:modelValue': [value: ViewMode]
}>()
</script>

<template>
  <div class="flex items-center border rounded-md">
    <Button
      type="button"
      variant="ghost"
      size="sm"
      :class="[
        'gap-2 rounded-r-none',
        modelValue === 'table' ? 'bg-muted' : ''
      ]"
      @click="emit('update:modelValue', 'table')"
    >
      <TableIcon class="h-4 w-4" />
      <span class="sr-only md:not-sr-only">Table</span>
    </Button>
    <Button
      type="button"
      variant="ghost"
      size="sm"
      :class="[
        'gap-2 rounded-l-none',
        modelValue === 'grid' ? 'bg-muted' : ''
      ]"
      @click="emit('update:modelValue', 'grid')"
    >
      <LayoutGridIcon class="h-4 w-4" />
      <span class="sr-only md:not-sr-only">Grid</span>
    </Button>
  </div>
</template>

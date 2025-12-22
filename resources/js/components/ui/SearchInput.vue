<script setup lang="ts">
/**
 * SearchInput Component
 *
 * Search input with icon, debounce, and clear button.
 */
import { ref, watch } from 'vue'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { SearchIcon, XIcon } from 'lucide-vue-next'
import { useDebounceFn } from '@vueuse/core'

const props = withDefaults(defineProps<{
  modelValue: string
  placeholder?: string
  debounce?: number
}>(), {
  placeholder: 'Search...',
  debounce: 300,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'search', value: string): void
}>()

const localValue = ref(props.modelValue)

watch(() => props.modelValue, (value) => {
  localValue.value = value
})

const debouncedSearch = useDebounceFn((value: string) => {
  emit('update:modelValue', value)
  emit('search', value)
}, props.debounce)

const handleInput = (event: Event) => {
  const value = (event.target as HTMLInputElement).value
  localValue.value = value
  debouncedSearch(value)
}

const handleClear = () => {
  localValue.value = ''
  emit('update:modelValue', '')
  emit('search', '')
}
</script>

<template>
  <div class="relative">
    <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
    <Input
      :value="localValue"
      @input="handleInput"
      :placeholder="placeholder"
      class="pl-9 pr-9"
    />
    <Button
      v-if="localValue"
      variant="ghost"
      size="icon"
      class="absolute right-1 top-1/2 -translate-y-1/2 h-7 w-7"
      @click="handleClear"
    >
      <XIcon class="h-4 w-4" />
    </Button>
  </div>
</template>

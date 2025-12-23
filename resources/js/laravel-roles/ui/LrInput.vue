<script setup lang="ts">
import { type HTMLAttributes } from 'vue'

interface Props {
  type?: 'text' | 'email' | 'password' | 'search' | 'number'
  placeholder?: string
  disabled?: boolean
  modelValue?: string | number
  class?: HTMLAttributes['class']
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  disabled: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const handleInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  emit('update:modelValue', target.value)
}
</script>

<template>
  <input
    :type="type"
    :value="modelValue"
    :placeholder="placeholder"
    :disabled="disabled"
    :class="[
      'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm',
      'ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium',
      'placeholder:text-muted-foreground',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
      'disabled:cursor-not-allowed disabled:opacity-50',
      props.class,
    ]"
    @input="handleInput"
  />
</template>

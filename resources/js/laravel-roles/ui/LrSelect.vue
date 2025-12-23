<script setup lang="ts">
import { ref, computed, type HTMLAttributes } from 'vue'

interface Option {
  value: string
  label: string
}

interface Props {
  modelValue?: string
  options: Option[]
  placeholder?: string
  disabled?: boolean
  class?: HTMLAttributes['class']
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Select...',
  disabled: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const isOpen = ref(false)

const selectedLabel = computed(() => {
  const option = props.options.find(o => o.value === props.modelValue)
  return option?.label || props.placeholder
})

const toggle = () => {
  if (!props.disabled) {
    isOpen.value = !isOpen.value
  }
}

const select = (value: string) => {
  emit('update:modelValue', value)
  isOpen.value = false
}

const closeOnClickOutside = (event: MouseEvent) => {
  const target = event.target as HTMLElement
  if (!target.closest('.lr-select')) {
    isOpen.value = false
  }
}

// Close on escape
const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    isOpen.value = false
  }
}
</script>

<template>
  <div
    class="lr-select relative"
    @keydown="handleKeydown"
    v-click-outside="() => isOpen = false"
  >
    <button
      type="button"
      :disabled="disabled"
      :class="[
        'flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm',
        'ring-offset-background placeholder:text-muted-foreground',
        'focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-50',
        props.class,
      ]"
      @click="toggle"
    >
      <span :class="{ 'text-muted-foreground': !modelValue }">
        {{ selectedLabel }}
      </span>
      <svg
        class="h-4 w-4 opacity-50 transition-transform"
        :class="{ 'rotate-180': isOpen }"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <Transition name="select">
      <div
        v-if="isOpen"
        class="absolute z-50 mt-1 w-full rounded-md border bg-popover text-popover-foreground shadow-md"
      >
        <div class="max-h-60 overflow-auto p-1">
          <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :class="[
              'relative flex w-full cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none',
              'hover:bg-accent hover:text-accent-foreground',
              modelValue === option.value ? 'bg-accent text-accent-foreground' : '',
            ]"
            @click="select(option.value)"
          >
            {{ option.label }}
            <svg
              v-if="modelValue === option.value"
              class="ml-auto h-4 w-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.select-enter-active,
.select-leave-active {
  transition: all 0.15s ease;
}

.select-enter-from,
.select-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>

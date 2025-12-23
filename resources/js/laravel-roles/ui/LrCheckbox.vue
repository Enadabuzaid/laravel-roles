<script setup lang="ts">
interface Props {
  modelValue?: boolean
  disabled?: boolean
  id?: string
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: false,
  disabled: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const handleChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  emit('update:modelValue', target.checked)
}
</script>

<template>
  <input
    type="checkbox"
    :id="id"
    :checked="modelValue"
    :disabled="disabled"
    class="peer h-4 w-4 shrink-0 rounded-sm border border-primary ring-offset-background
      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2
      disabled:cursor-not-allowed disabled:opacity-50
      data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground"
    @change="handleChange"
  />
</template>

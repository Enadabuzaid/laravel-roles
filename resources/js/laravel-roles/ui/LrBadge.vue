<script setup lang="ts">
import { type HTMLAttributes } from 'vue'

interface Props {
  variant?: 'default' | 'secondary' | 'destructive' | 'outline'
  class?: HTMLAttributes['class']
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
})

const variantClasses: Record<string, string> = {
  default: 'bg-primary text-primary-foreground hover:bg-primary/80',
  secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
  destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/80',
  outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
}
</script>

<template>
  <div
    :class="[
      'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold transition-colors',
      'focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
      variantClasses[variant],
      props.class,
    ]"
  >
    <slot />
  </div>
</template>

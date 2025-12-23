<script setup lang="ts">
import { type HTMLAttributes } from 'vue'

interface Props {
  class?: HTMLAttributes['class']
}

const props = defineProps<Props>()
</script>

<template>
  <div :class="['rounded-lg border bg-card text-card-foreground shadow-sm', props.class]">
    <slot />
  </div>
</template>

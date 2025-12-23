<script setup lang="ts">
import { type HTMLAttributes } from 'vue'

interface Props {
  class?: HTMLAttributes['class']
}

const props = defineProps<Props>()
</script>

<template>
  <p :class="['text-sm text-muted-foreground', props.class]">
    <slot />
  </p>
</template>

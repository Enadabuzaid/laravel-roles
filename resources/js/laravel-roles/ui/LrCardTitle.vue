<script setup lang="ts">
import { type HTMLAttributes } from 'vue'

interface Props {
  class?: HTMLAttributes['class']
}

const props = defineProps<Props>()
</script>

<template>
  <h3 :class="['text-2xl font-semibold leading-none tracking-tight', props.class]">
    <slot />
  </h3>
</template>

<script setup lang="ts">
import { type HTMLAttributes } from 'vue'

interface Props {
  class?: HTMLAttributes['class']
}

const props = defineProps<Props>()
</script>

<template>
  <div :class="['flex flex-col space-y-1.5 p-6', props.class]">
    <slot />
  </div>
</template>

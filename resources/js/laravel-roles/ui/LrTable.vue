<script setup lang="ts">
import { type HTMLAttributes } from 'vue'

interface Props {
  class?: HTMLAttributes['class']
}

const props = defineProps<Props>()
</script>

<template>
  <div class="relative w-full overflow-auto">
    <table :class="['w-full caption-bottom text-sm', props.class]">
      <slot />
    </table>
  </div>
</template>

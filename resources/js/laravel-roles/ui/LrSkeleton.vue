<script setup lang="ts">
import { type HTMLAttributes } from 'vue'

interface Props {
  class?: HTMLAttributes['class']
}

const props = defineProps<Props>()
</script>

<template>
  <div :class="['animate-pulse rounded-md bg-muted', props.class]" />
</template>

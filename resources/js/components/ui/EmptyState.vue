<script setup lang="ts">
/**
 * EmptyState Component
 *
 * Reusable empty state with icon, title, description, and CTA.
 */
import { Button } from '@/components/ui/button'
import { FolderOpenIcon } from 'lucide-vue-next'

const props = withDefaults(defineProps<{
  title: string
  description?: string
  actionLabel?: string
  actionHref?: string
}>(), {})

const emit = defineEmits<{
  (e: 'action'): void
}>()
</script>

<template>
  <div class="flex flex-col items-center justify-center py-12 text-center">
    <div class="rounded-full bg-muted p-4 mb-4">
      <slot name="icon">
        <FolderOpenIcon class="h-8 w-8 text-muted-foreground" />
      </slot>
    </div>

    <h3 class="text-lg font-semibold">{{ title }}</h3>

    <p v-if="description" class="text-muted-foreground mt-2 max-w-md">
      {{ description }}
    </p>

    <div v-if="actionLabel" class="mt-4">
      <Button
        v-if="actionHref"
        as="a"
        :href="actionHref"
      >
        {{ actionLabel }}
      </Button>
      <Button
        v-else
        @click="emit('action')"
      >
        {{ actionLabel }}
      </Button>
    </div>

    <slot />
  </div>
</template>

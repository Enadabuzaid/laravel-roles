<script setup lang="ts">
import LrButton from '@/laravel-roles/ui/LrButton.vue'

interface Props {
  icon?: 'inbox' | 'search' | 'shield' | 'key'
  title: string
  description?: string
  actionLabel?: string
  actionHref?: string
}

const props = withDefaults(defineProps<Props>(), {
  icon: 'inbox',
})

const emit = defineEmits<{
  action: []
}>()
</script>

<template>
  <div class="flex flex-col items-center justify-center py-12 text-center">
    <!-- Icon -->
    <div class="mb-4 rounded-full bg-muted p-3">
      <svg v-if="icon === 'inbox'" class="h-6 w-6 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
      </svg>
      <svg v-else-if="icon === 'search'" class="h-6 w-6 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
      <svg v-else-if="icon === 'shield'" class="h-6 w-6 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
      </svg>
      <svg v-else-if="icon === 'key'" class="h-6 w-6 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
      </svg>
    </div>

    <!-- Text -->
    <h3 class="text-lg font-semibold">{{ title }}</h3>
    <p v-if="description" class="mt-1 text-sm text-muted-foreground max-w-sm">
      {{ description }}
    </p>

    <!-- Action -->
    <div v-if="actionLabel" class="mt-4">
      <LrButton
        v-if="actionHref"
        @click="$router?.push(actionHref) || (window.location.href = actionHref)"
      >
        {{ actionLabel }}
      </LrButton>
      <LrButton v-else @click="emit('action')">
        {{ actionLabel }}
      </LrButton>
    </div>
  </div>
</template>

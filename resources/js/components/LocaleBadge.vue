<script setup lang="ts">
import { Badge } from '@/components/ui/badge'

defineProps<{
  locale: string
}>()

const localeColors: Record<string, string> = {
  en: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
  ar: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
  fr: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300'
}
</script>

<template>
  <Badge
    :class="localeColors[locale] || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'"
    variant="outline"
    class="text-xs uppercase font-medium"
  >
    {{ locale }}
  </Badge>
</template>


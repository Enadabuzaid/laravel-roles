<script setup lang="ts">
import { computed } from 'vue'

interface Breadcrumb {
  label: string
  href?: string
}

interface Props {
  title: string
  description?: string
  breadcrumbs?: Breadcrumb[]
}

const props = defineProps<Props>()

// Get prefix from config
const getPrefix = () => {
  if (typeof window !== 'undefined') {
    return (window as any).laravelRoles?.uiPrefix || 'admin/acl'
  }
  return 'admin/acl'
}
</script>

<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <nav v-if="breadcrumbs && breadcrumbs.length > 0" class="flex" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-3">
        <li v-for="(crumb, index) in breadcrumbs" :key="index" class="inline-flex items-center">
          <span v-if="index > 0" class="mx-2 text-muted-foreground">/</span>
          <a
            v-if="crumb.href"
            :href="crumb.href"
            class="text-sm font-medium text-muted-foreground hover:text-foreground"
          >
            {{ crumb.label }}
          </a>
          <span
            v-else
            class="text-sm font-medium text-foreground"
          >
            {{ crumb.label }}
          </span>
        </li>
      </ol>
    </nav>

    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold tracking-tight">{{ title }}</h1>
        <p v-if="description" class="text-muted-foreground">{{ description }}</p>
      </div>
      <div class="flex items-center gap-2">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>

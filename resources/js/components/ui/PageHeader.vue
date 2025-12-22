<script setup lang="ts">
/**
 * PageHeader Component
 *
 * Consistent page header with title, description, breadcrumbs, and action slot.
 */
import { computed } from 'vue'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import { HomeIcon } from 'lucide-vue-next'

export interface BreadcrumbItem {
  label: string
  href?: string
}

const props = withDefaults(defineProps<{
  title: string
  description?: string
  breadcrumbs?: BreadcrumbItem[]
  homeHref?: string
}>(), {
  homeHref: '/admin',
  breadcrumbs: () => [],
})

const hasBreadcrumbs = computed(() => props.breadcrumbs.length > 0)
</script>

<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <Breadcrumb v-if="hasBreadcrumbs">
      <BreadcrumbList>
        <BreadcrumbItem>
          <BreadcrumbLink :href="homeHref">
            <HomeIcon class="h-4 w-4" />
          </BreadcrumbLink>
        </BreadcrumbItem>

        <template v-for="(item, index) in breadcrumbs" :key="index">
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <BreadcrumbLink v-if="item.href" :href="item.href">
              {{ item.label }}
            </BreadcrumbLink>
            <BreadcrumbPage v-else>
              {{ item.label }}
            </BreadcrumbPage>
          </BreadcrumbItem>
        </template>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Header Content -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-3xl font-bold tracking-tight">
          {{ title }}
        </h1>
        <p v-if="description" class="text-muted-foreground mt-1">
          {{ description }}
        </p>
      </div>

      <!-- Actions Slot -->
      <div class="flex items-center gap-2">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>

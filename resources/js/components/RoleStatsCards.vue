<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { UsersIcon, ShieldCheckIcon, ShieldXIcon, ActivityIcon } from 'lucide-vue-next'
import type { RoleStats } from '@/laravel-roles/types'

const props = defineProps<{
  stats: RoleStats | null
  loading?: boolean
}>()

const statsCards = computed(() => [
  {
    title: 'roles.stats.total_roles',
    value: props.stats?.total || 0,
    icon: ShieldCheckIcon,
    color: 'text-blue-600 dark:text-blue-400',
    bgColor: 'bg-blue-100 dark:bg-blue-900/20'
  },
  {
    title: 'roles.stats.with_permissions',
    value: props.stats?.with_permissions || 0,
    icon: ActivityIcon,
    color: 'text-green-600 dark:text-green-400',
    bgColor: 'bg-green-100 dark:bg-green-900/20'
  },
  {
    title: 'roles.stats.without_permissions',
    value: props.stats?.without_permissions || 0,
    icon: ShieldXIcon,
    color: 'text-orange-600 dark:text-orange-400',
    bgColor: 'bg-orange-100 dark:bg-orange-900/20'
  },
  {
    title: 'roles.stats.active',
    value: props.stats?.active || props.stats?.total || 0,
    icon: UsersIcon,
    color: 'text-purple-600 dark:text-purple-400',
    bgColor: 'bg-purple-100 dark:bg-purple-900/20'
  }
])

// Fallback label function
const getLabel = (key: string) => {
  const labels: Record<string, string> = {
    'roles.stats.total_roles': 'Total Roles',
    'roles.stats.with_permissions': 'With Permissions',
    'roles.stats.without_permissions': 'Without Permissions',
    'roles.stats.active': 'Active Roles'
  }
  return labels[key] || key
}
</script>

<template>
  <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
    <Card v-for="stat in statsCards" :key="stat.title">
      <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle class="text-sm font-medium">
          {{ getLabel(stat.title) }}
        </CardTitle>
        <component
          :is="stat.icon"
          :class="['h-4 w-4', stat.color]"
        />
      </CardHeader>
      <CardContent>
        <div v-if="loading" class="h-8 w-16 bg-muted animate-pulse rounded" />
        <div v-else class="text-2xl font-bold">{{ stat.value }}</div>
      </CardContent>
    </Card>
  </div>
</template>

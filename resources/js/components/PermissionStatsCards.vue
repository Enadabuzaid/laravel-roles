<script setup lang="ts">
</template>
  </div>
    </Card>
      </CardContent>
        <div v-else class="text-2xl font-bold">{{ stat.value }}</div>
        <div v-if="loading" class="h-8 w-16 bg-muted animate-pulse rounded" />
      <CardContent>
      </CardHeader>
        />
          :class="['h-4 w-4', stat.color]"
          :is="stat.icon"
        <component
        </CardTitle>
          {{ $t(stat.title) }}
        <CardTitle class="text-sm font-medium">
      <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
    <Card v-for="stat in statsCards" :key="stat.title">
  <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
<template>

</script>
])
  }
    bgColor: 'bg-orange-100 dark:bg-orange-900/20'
    color: 'text-orange-600 dark:text-orange-400',
    icon: UnlinkIcon,
    value: props.stats?.not_assigned || 0,
    title: 'roles.stats.not_assigned',
  {
  },
    bgColor: 'bg-green-100 dark:bg-green-900/20'
    color: 'text-green-600 dark:text-green-400',
    icon: LinkIcon,
    value: props.stats?.assigned_to_roles || 0,
    title: 'roles.stats.assigned',
  {
  },
    bgColor: 'bg-purple-100 dark:bg-purple-900/20'
    color: 'text-purple-600 dark:text-purple-400',
    icon: LayersIcon,
    value: Object.keys(props.stats?.by_group || {}).length,
    title: 'roles.stats.permission_groups',
  {
  },
    bgColor: 'bg-blue-100 dark:bg-blue-900/20'
    color: 'text-blue-600 dark:text-blue-400',
    icon: KeyIcon,
    value: props.stats?.total || 0,
    title: 'roles.stats.total_permissions',
  {
const statsCards = computed(() => [

}>()
  loading?: boolean
  stats: PermissionStats | null
const props = defineProps<{

import type { PermissionStats } from '@/types'
import { KeyIcon, LayersIcon, LinkIcon, UnlinkIcon } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { computed } from 'vue'


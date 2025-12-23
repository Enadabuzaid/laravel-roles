<script setup lang="ts">
import { computed } from 'vue'
import LrCard from '@/laravel-roles/ui/LrCard.vue'
import LrCardHeader from '@/laravel-roles/ui/LrCardHeader.vue'
import LrCardTitle from '@/laravel-roles/ui/LrCardTitle.vue'
import LrCardContent from '@/laravel-roles/ui/LrCardContent.vue'
import LrSkeleton from '@/laravel-roles/ui/LrSkeleton.vue'

interface StatCard {
  title: string
  value: number | string
  icon: 'shield' | 'key' | 'users' | 'activity' | 'link' | 'unlink' | 'layers'
  color: string
}

interface Props {
  stats: StatCard[]
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const icons = {
  shield: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
  key: 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
  users: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
  activity: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
  link: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
  unlink: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 015.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1M2 2l20 20',
  layers: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
}
</script>

<template>
  <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
    <LrCard v-for="(stat, index) in stats" :key="index">
      <LrCardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
        <LrCardTitle class="text-sm font-medium">
          {{ stat.title }}
        </LrCardTitle>
        <svg
          :class="['h-4 w-4', stat.color]"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            :d="icons[stat.icon] || icons.shield"
          />
        </svg>
      </LrCardHeader>
      <LrCardContent>
        <LrSkeleton v-if="loading" class="h-8 w-16" />
        <div v-else class="text-2xl font-bold">{{ stat.value }}</div>
      </LrCardContent>
    </LrCard>
  </div>
</template>

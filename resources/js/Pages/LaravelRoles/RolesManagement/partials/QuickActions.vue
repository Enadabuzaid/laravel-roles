<script setup lang="ts">
defineProps<{ apiPrefix: string; rolesCount: number; permissionsCount: number }>()
</script>

<template>
  <div class="rounded-xl border bg-gradient-to-r from-primary/5 to-transparent p-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h3 class="font-semibold">Quick Overview</h3>
        <p class="text-sm text-muted-foreground">You have <span class="font-medium text-foreground">{{ rolesCount }}</span> roles with <span class="font-medium text-foreground">{{ permissionsCount }}</span> permissions configured</p>
      </div>
      <div class="flex items-center gap-2">
        <a :href="`/${apiPrefix}/roles/create`" class="inline-flex items-center gap-2 rounded-lg border bg-background px-3 py-2 text-sm font-medium hover:bg-muted transition-colors">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          New Role
        </a>
        <a :href="`/${apiPrefix}/matrix`" class="inline-flex items-center gap-2 rounded-lg bg-primary/10 px-3 py-2 text-sm font-medium text-primary hover:bg-primary/20 transition-colors">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
          Open Matrix
        </a>
      </div>
    </div>
  </div>
</template>

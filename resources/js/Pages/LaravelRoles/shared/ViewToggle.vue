<script setup lang="ts">
defineProps<{ modelValue: 'list' | 'grid' }>()
const emit = defineEmits<{ 'update:modelValue': [value: 'list' | 'grid'] }>()
</script>

<template>
  <div class="inline-flex items-center rounded-lg border bg-muted p-1">
    <button @click="emit('update:modelValue', 'list')" :class="['inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-all', modelValue === 'list' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground']">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
      List
    </button>
    <button @click="emit('update:modelValue', 'grid')" :class="['inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-all', modelValue === 'grid' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground']">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
      Grid
    </button>
  </div>
</template>

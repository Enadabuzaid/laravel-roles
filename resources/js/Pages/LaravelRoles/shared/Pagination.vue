<script setup lang="ts">
interface Meta { current_page: number; last_page: number; per_page: number; total: number; from: number; to: number }
defineProps<{ meta: Meta }>()
const emit = defineEmits<{ page: [page: number] }>()
const pages = (c: number, l: number) => { const p: number[] = []; for (let i = Math.max(1, c - 2); i <= Math.min(l, c + 2); i++) p.push(i); return p }
</script>

<template>
  <div class="flex items-center justify-between border-t px-4 py-3">
    <div class="text-sm text-muted-foreground">Showing {{ meta.from }} to {{ meta.to }} of {{ meta.total }}</div>
    <div class="flex items-center gap-1">
      <button :disabled="meta.current_page === 1" @click="emit('page', meta.current_page - 1)" class="inline-flex h-9 items-center justify-center rounded-lg border px-3 text-sm font-medium disabled:opacity-50 hover:bg-muted transition-colors">Previous</button>
      <button v-for="p in pages(meta.current_page, meta.last_page)" :key="p" @click="emit('page', p)" :class="['inline-flex h-9 w-9 items-center justify-center rounded-lg text-sm font-medium transition-colors', meta.current_page === p ? 'bg-primary text-primary-foreground' : 'border hover:bg-muted']">{{ p }}</button>
      <button :disabled="meta.current_page === meta.last_page" @click="emit('page', meta.current_page + 1)" class="inline-flex h-9 items-center justify-center rounded-lg border px-3 text-sm font-medium disabled:opacity-50 hover:bg-muted transition-colors">Next</button>
    </div>
  </div>
</template>

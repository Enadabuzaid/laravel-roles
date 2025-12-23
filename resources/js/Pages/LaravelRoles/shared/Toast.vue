<script setup lang="ts">
import { ref, onMounted } from 'vue'
interface Toast { id: number; type: 'success' | 'error' | 'info'; message: string }
const toasts = ref<Toast[]>([])
let id = 0
const add = (msg: string, type: Toast['type'] = 'success') => { const tid = ++id; toasts.value.push({ id: tid, type, message: msg }); setTimeout(() => remove(tid), 4000) }
const remove = (tid: number) => { const i = toasts.value.findIndex(t => t.id === tid); if (i > -1) toasts.value.splice(i, 1) }
onMounted(() => { (window as any).__lr_toast = { success: (m: string) => add(m, 'success'), error: (m: string) => add(m, 'error'), info: (m: string) => add(m, 'info') } })
const bg = (t: string) => t === 'success' ? 'bg-green-600' : t === 'error' ? 'bg-red-600' : 'bg-blue-600'
const icon = (t: string) => t === 'success' ? 'M5 13l4 4L19 7' : t === 'error' ? 'M6 18L18 6M6 6l12 12' : 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
</script>

<template>
  <Teleport to="body">
    <div class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2">
      <TransitionGroup name="toast">
        <div v-for="t in toasts" :key="t.id" :class="[bg(t.type), 'flex items-center gap-3 rounded-lg px-4 py-3 text-white shadow-lg']">
          <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="icon(t.type)" /></svg>
          <span class="text-sm font-medium">{{ t.message }}</span>
          <button @click="remove(t.id)" class="ml-2 hover:opacity-80"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; } .toast-enter-from { opacity: 0; transform: translateX(100%); } .toast-leave-to { opacity: 0; transform: translateX(100%); }</style>

<script setup lang="ts">
defineProps<{ open: boolean; title: string; description?: string; confirmLabel?: string; cancelLabel?: string; loading?: boolean; destructive?: boolean }>()
const emit = defineEmits<{ 'update:open': [value: boolean]; confirm: []; cancel: [] }>()
const close = () => { emit('update:open', false); emit('cancel') }
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="close" />
        <div class="relative z-50 w-full max-w-md rounded-xl bg-background p-6 shadow-2xl">
          <h3 class="text-lg font-semibold">{{ title }}</h3>
          <p v-if="description" class="mt-2 text-sm text-muted-foreground">{{ description }}</p>
          <div class="mt-6 flex justify-end gap-3">
            <button @click="close" :disabled="loading" class="inline-flex h-10 items-center rounded-lg border px-4 text-sm font-medium hover:bg-muted transition-colors disabled:opacity-50">{{ cancelLabel || 'Cancel' }}</button>
            <button @click="emit('confirm')" :disabled="loading" :class="['inline-flex h-10 items-center rounded-lg px-4 text-sm font-medium transition-colors disabled:opacity-50', destructive ? 'bg-destructive text-destructive-foreground hover:bg-destructive/90' : 'bg-primary text-primary-foreground hover:bg-primary/90']">
              <svg v-if="loading" class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
              {{ confirmLabel || 'Confirm' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: all 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>

<script setup lang="ts">
import { ref, watch } from 'vue'
import LrButton from './LrButton.vue'

interface Props {
  open?: boolean
  title?: string
  description?: string
  confirmLabel?: string
  cancelLabel?: string
  destructive?: boolean
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
  title: 'Are you sure?',
  description: 'This action cannot be undone.',
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  destructive: false,
  loading: false,
})

const emit = defineEmits<{
  'update:open': [value: boolean]
  confirm: []
  cancel: []
}>()

const close = () => {
  emit('update:open', false)
  emit('cancel')
}

const confirm = () => {
  emit('confirm')
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center"
    >
      <!-- Backdrop -->
      <div
        class="fixed inset-0 bg-black/80 transition-opacity"
        @click="close"
      />
      
      <!-- Dialog -->
      <div class="relative z-50 w-full max-w-lg rounded-lg border bg-background p-6 shadow-lg">
        <div class="flex flex-col space-y-2 text-center sm:text-left">
          <h2 class="text-lg font-semibold">{{ title }}</h2>
          <p class="text-sm text-muted-foreground">{{ description }}</p>
        </div>
        
        <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
          <LrButton
            variant="outline"
            :disabled="loading"
            @click="close"
          >
            {{ cancelLabel }}
          </LrButton>
          <LrButton
            :variant="destructive ? 'destructive' : 'default'"
            :loading="loading"
            @click="confirm"
          >
            {{ confirmLabel }}
          </LrButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>

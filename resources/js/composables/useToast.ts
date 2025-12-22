/**
 * useToast Composable
 *
 * Toast notification helper for the package.
 * Integrates with shadcn-vue toast or provides fallback.
 */
import { ref, readonly } from 'vue'
import type { Toast, ToastType } from '@/types'

// Global toast queue
const toasts = ref<Toast[]>([])
let toastId = 0

/**
 * Add a toast notification
 */
function addToast(toast: Omit<Toast, 'id'>): string {
    const id = `toast-${++toastId}`
    const newToast: Toast = {
        id,
        ...toast,
        duration: toast.duration ?? 5000,
    }

    toasts.value.push(newToast)

    // Auto-remove after duration
    if (newToast.duration && newToast.duration > 0) {
        setTimeout(() => {
            removeToast(id)
        }, newToast.duration)
    }

    return id
}

/**
 * Remove a toast by ID
 */
function removeToast(id: string): void {
    const index = toasts.value.findIndex(t => t.id === id)
    if (index !== -1) {
        toasts.value.splice(index, 1)
    }
}

/**
 * Clear all toasts
 */
function clearToasts(): void {
    toasts.value = []
}

/**
 * Shorthand methods
 */
function success(title: string, description?: string): string {
    return addToast({ type: 'success', title, description })
}

function error(title: string, description?: string): string {
    return addToast({ type: 'error', title, description })
}

function warning(title: string, description?: string): string {
    return addToast({ type: 'warning', title, description })
}

function info(title: string, description?: string): string {
    return addToast({ type: 'info', title, description })
}

/**
 * Main composable export
 */
export function useToast() {
    return {
        toasts: readonly(toasts),
        addToast,
        removeToast,
        clearToasts,
        success,
        error,
        warning,
        info,
    }
}

export default useToast

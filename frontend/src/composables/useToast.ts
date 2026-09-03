import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'warning' | 'info'

export interface ToastItem {
  id: string
  title?: string
  message: string
  type: ToastType
  duration?: number
}

const toasts = ref<ToastItem[]>([])

export function useToast() {
  function show(message: string, type: ToastType = 'info', title?: string, duration: number = 4000): string {
    const id = Math.random().toString(36).substring(2, 9)
    const toast: ToastItem = { id, title, message, type, duration }

    toasts.value.push(toast)

    if (duration > 0) {
      setTimeout(() => {
        remove(id)
      }, duration)
    }

    return id
  }

  function success(message: string, title?: string, duration?: number): string {
    return show(message, 'success', title, duration)
  }

  function error(message: string, title?: string, duration: number = 5000): string {
    return show(message, 'error', title, duration)
  }

  function warning(message: string, title?: string, duration?: number): string {
    return show(message, 'warning', title, duration)
  }

  function info(message: string, title?: string, duration?: number): string {
    return show(message, 'info', title, duration)
  }

  function remove(id: string): void {
    const index = toasts.value.findIndex(t => t.id === id)
    if (index !== -1) {
      toasts.value.splice(index, 1)
    }
  }

  function clear(): void {
    toasts.value = []
  }

  return {
    toasts,
    show,
    success,
    error,
    warning,
    info,
    remove,
    clear,
  }
}

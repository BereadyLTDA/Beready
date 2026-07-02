import { ref, readonly } from 'vue'

interface ToastMessage {
  id: number
  title: string
  message: string
  type: 'success' | 'error' | 'warning' | 'info'
  duration: number
}

const toasts = ref<ToastMessage[]>([])
let nextId = 0

export function useToast() {
  const showToast = (options: {
    title?: string
    message: string
    type?: 'success' | 'error' | 'warning' | 'info'
    duration?: number
  }) => {
    const id = nextId++
    const toast: ToastMessage = {
      id,
      title: options.title || getDefaultTitle(options.type || 'info'),
      message: options.message,
      type: options.type || 'info',
      duration: options.duration || 3000,
    }

    toasts.value.push(toast)

    setTimeout(() => {
      removeToast(id)
    }, toast.duration)

    return id
  }

  const removeToast = (id: number) => {
    const index = toasts.value.findIndex((t) => t.id === id)
    if (index !== -1) {
      toasts.value.splice(index, 1)
    }
  }

  const getDefaultTitle = (type: string): string => {
    switch (type) {
      case 'success':
        return 'Sucesso!'
      case 'error':
        return 'Erro!'
      case 'warning':
        return 'Atenção!'
      default:
        return 'Informação'
    }
  }

  const success = (message: string, title?: string, duration?: number) => {
    return showToast({ title, message, type: 'success', duration })
  }

  const error = (message: string, title?: string, duration?: number) => {
    return showToast({ title, message, type: 'error', duration })
  }

  const warning = (message: string, title?: string, duration?: number) => {
    return showToast({ title, message, type: 'warning', duration })
  }

  const info = (message: string, title?: string, duration?: number) => {
    return showToast({ title, message, type: 'info', duration })
  }

  return {
    toasts: readonly(toasts),
    showToast,
    removeToast,
    success,
    error,
    warning,
    info,
  }
}

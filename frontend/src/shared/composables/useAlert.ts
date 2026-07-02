import { ref } from 'vue'

export interface Alert {
  id: number
  message: string
  type: 'success' | 'error' | 'warning' | 'info'
  duration: number
}

const alerts = ref<Alert[]>([])
let nextId = 0

export function useAlert() {
  const addAlert = (message: string, type: Alert['type'] = 'info', duration: number = 3000) => {
    const id = nextId++
    alerts.value.push({ id, message, type, duration })
    setTimeout(() => removeAlert(id), duration)
  }

  const removeAlert = (id: number) => {
    const index = alerts.value.findIndex((alert) => alert.id === id)
    if (index !== -1) alerts.value.splice(index, 1)
  }

  // showAlert com a mesma assinatura de addAlert (message, type, duration)
  const showAlert = addAlert

  const success = (message: string, duration?: number) => addAlert(message, 'success', duration)
  const error = (message: string, duration?: number) => addAlert(message, 'error', duration)
  const warning = (message: string, duration?: number) => addAlert(message, 'warning', duration)
  const info = (message: string, duration?: number) => addAlert(message, 'info', duration)
  const clearAllAlerts = () => {
    alerts.value = []
  }

  return {
    alerts,
    addAlert,
    showAlert,
    removeAlert,
    success,
    error,
    warning,
    info,
    clearAllAlerts,
  }
}

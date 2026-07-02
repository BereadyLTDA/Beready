import { ref, onMounted, onUnmounted } from 'vue'

export function useUser() {
  const user = ref(JSON.parse(localStorage.getItem('user') || '{}'))

  const loadUser = () => {
    const userData = localStorage.getItem('user')
    if (userData) {
      user.value = JSON.parse(userData)
    }
  }

  const handleUserUpdated = (event: CustomEvent) => {
    user.value = event.detail
    localStorage.setItem('user', JSON.stringify(event.detail))
  }

  onMounted(() => {
    loadUser()
    window.addEventListener('user-updated', handleUserUpdated as EventListener)
  })

  onUnmounted(() => {
    window.removeEventListener('user-updated', handleUserUpdated as EventListener)
  })

  return {
    user,
    loadUser,
  }
}

import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

export function useHome() {
  const router = useRouter()

  onMounted(() => {
    const user = localStorage.getItem('user')
    if (user) {
      router.push('/dashboard')
    }
  })

  return {}
}

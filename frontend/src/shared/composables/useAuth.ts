import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/core/services/api'

export interface User {
  id: number
  nome: string
  email: string
  role: 'user' | 'admin'
  telefone?: string
  nivel_ingles?: string
  idioma_preferido?: string
  objetivos_aprendizado?: string
  status?: string
  uuid?: string
}

export function useAuth() {
  const router = useRouter()
  const loading = ref(false)
  const user = ref<User | null>(null)

  const isAuthenticated = computed(() => !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isUser = computed(() => user.value?.role === 'user')

  const hasRole = (role: string | string[]) => {
    if (!user.value) return false
    if (Array.isArray(role)) {
      return role.includes(user.value.role)
    }
    return user.value.role === role
  }

  const login = async (email: string, password: string) => {
    loading.value = true
    try {
      const response = await api.post('/auth/login', { email, password })
      if (response.data.success) {
        const { user: userData, tokens } = response.data.data
        localStorage.setItem('user', JSON.stringify(userData))
        localStorage.setItem('access_token', tokens.access_token)
        localStorage.setItem('refresh_token', tokens.refresh_token)
        user.value = userData
        return { success: true, user: userData }
      }
      return { success: false, message: response.data.message }
    } catch (error: any) {
      return { success: false, message: error.response?.data?.message || 'Erro de conexão' }
    } finally {
      loading.value = false
    }
  }

  const logout = async () => {
    loading.value = true
    try {
      await api.post('/auth/logout')
    } catch (error) {
      console.error('Erro no logout:', error)
    } finally {
      localStorage.removeItem('user')
      localStorage.removeItem('access_token')
      localStorage.removeItem('refresh_token')
      user.value = null
      loading.value = false
      router.push('/login')
    }
  }

  const loadUser = () => {
    const userData = localStorage.getItem('user')
    if (userData) {
      try {
        user.value = JSON.parse(userData)
      } catch (e) {
        console.error('Erro ao carregar usuário:', e)
        localStorage.removeItem('user')
      }
    }
    return user.value
  }

  const refreshUser = async () => {
    try {
      const response = await api.get('/users/me')
      if (response.data.success) {
        user.value = response.data.data
        localStorage.setItem('user', JSON.stringify(user.value))
      }
    } catch (error) {
      console.error('Erro ao atualizar usuário:', error)
    }
  }

  // Carrega usuário na inicialização
  loadUser()

  return {
    user,
    loading,
    isAuthenticated,
    isAdmin,
    isUser,
    hasRole,
    login,
    logout,
    loadUser,
    refreshUser,
  }
}

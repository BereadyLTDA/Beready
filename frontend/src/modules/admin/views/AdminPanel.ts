import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAlert } from '@/shared/composables/useAlert'
import api from '@/core/services/api'
import type { AxiosError } from 'axios'
import { useAuthStore } from '@/stores/auth'
import type { User, UserRole } from '@/core/types'

// TIPOS
interface Stats {
  total_users: number
  admin_count: number
  user_count: number
  total_flashcards: number
  total_quizes: number
  total_prompts: number
  total_tags: number
  total_traducoes: number
  total_imagens: number
  total_frases: number
}

interface ApiResponse<T> {
  success: boolean
  data: T
  message?: string
}

export function useAdminPanel() {
  const authStore = useAuthStore()
  const user = computed(() => authStore.user)
  const { success, error } = useAlert()
  const { t } = useI18n()
  const activeTab = ref<string>('users')
  const users = ref<User[]>([])
  const loadingUsers = ref<boolean>(false)
  const updatingRole = ref<number | null>(null)
  const searchQuery = ref<string>('')
  const stats = ref<Stats>({
    total_users: 0,
    admin_count: 0,
    user_count: 0,
    total_flashcards: 0,
    total_quizes: 0,
    total_prompts: 0,
    total_tags: 0,
    total_traducoes: 0,
    total_imagens: 0,
    total_frases: 0,
  })

  const currentUserId = computed<number | undefined>(() => user.value?.id)

  const filteredUsers = computed<User[]>(() => {
    if (!searchQuery.value) return users.value
    const query = searchQuery.value.toLowerCase()
    return users.value.filter(
      (u) => u.nome?.toLowerCase().includes(query) || u.email?.toLowerCase().includes(query),
    )
  })

  // 401 (sessão expirada) é tratado pelo interceptor do api (refresh/logout centralizado).
  // 403 significa apenas falta de permissão: mostra o erro, sem deslogar.
  const handleRequestError = (err: unknown, fallbackMessage: string): void => {
    const axiosError = err as AxiosError<{ message?: string }>
    const status = axiosError.response?.status

    if (status === 401) return

    if (status === 403) {
      error(axiosError.response?.data?.message || t('admin.noPermissionAction'))
      return
    }

    if (status === 500) {
      error(t('admin.serverError'))
      console.error('Detalhes do erro 500:', axiosError.response?.data)
      return
    }

    error(axiosError.response?.data?.message || axiosError.message || fallbackMessage)
  }

  const loadUsers = async (): Promise<void> => {
    loadingUsers.value = true
    try {
      const response = await api.get<ApiResponse<User[]>>('/admin/users')

      if (response.data && response.data.success) {
        users.value = response.data.data || []
      } else {
        console.error('Erro na resposta:', response.data?.message || 'Resposta inválida')
        users.value = []
        error(response.data?.message || t('admin.errorLoadUsers'))
      }
    } catch (err: unknown) {
      console.error('Erro ao carregar usuários:', err)
      handleRequestError(err, t('admin.errorLoadUsers'))
      users.value = []
    } finally {
      loadingUsers.value = false
    }
  }

  const loadStats = async (): Promise<void> => {
    try {
      const response = await api.get<ApiResponse<Stats>>('/admin/stats')
      if (response.data && response.data.success) {
        stats.value = response.data.data || stats.value
      } else {
        console.error('Erro na resposta:', response.data?.message)
        error(response.data?.message || t('admin.errorLoadStats'))
      }
    } catch (err: unknown) {
      console.error('Erro ao carregar estatísticas:', err)
      handleRequestError(err, t('admin.errorLoadStats'))
    }
  }

  const toggleRole = async (targetUser: User): Promise<void> => {
    // Verificar se o usuário atual é admin
    if (!isAdmin.value) {
      error(t('admin.noPermissionChangeRole'))
      return
    }

    // Não permitir alterar a própria role
    if (targetUser.id === user.value?.id) {
      error(t('admin.cannotChangeOwnRole'))
      return
    }

    const newRole: UserRole = targetUser.role === 'admin' ? 'user' : 'admin'

    updatingRole.value = targetUser.id

    try {
      const response = await api.post<ApiResponse<{ success: boolean }>>('/admin/users/role', {
        user_id: targetUser.id,
        role: newRole,
      })

      if (response.data && response.data.success) {
        await loadUsers()
        await loadStats() // Atualiza estatísticas após mudar role
        success(
          t(newRole === 'admin' ? 'admin.nowAdmin' : 'admin.nowUser', { name: targetUser.nome }),
        )
      } else {
        error(response.data?.message || t('admin.errorChangeRole'))
      }
    } catch (err: unknown) {
      console.error('Erro ao alterar role:', err)
      handleRequestError(err, t('admin.errorChangeRole'))
    } finally {
      updatingRole.value = null
    }
  }

  // Admin = role 'admin' vinda do servidor (store de auth)
  const isAdmin = computed<boolean>(() => authStore.isAdmin)

  // Função para recarregar todos os dados
  const reloadAll = async (): Promise<void> => {
    await Promise.all([
      loadUsers(),
      loadStats()
    ])
  }

  onMounted(async () => {
    // Verificar se o usuário tem permissão de admin antes de carregar
    if (isAdmin.value) {
      // Em paralelo: antes a lista de usuários esperava as estatísticas e vice-versa
      await Promise.all([loadUsers(), loadStats()])
    } else {
      error(t('admin.accessDeniedAdmin'))
    }
  })

  return {
    // State
    user,
    activeTab,
    users,
    loadingUsers,
    updatingRole,
    searchQuery,
    stats,
    currentUserId,
    filteredUsers,
    isAdmin,
    
    // Methods
    toggleRole,
    loadUsers,
    loadStats,
    reloadAll,
  }
}
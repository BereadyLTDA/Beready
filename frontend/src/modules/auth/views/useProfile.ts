// src/modules/auth/views/useProfile.ts
import { ref, onMounted, computed, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAlert } from '@/shared/composables/useAlert'
import api from '@/core/services/api'

export function useProfile() {
  const router = useRouter()
  const { success, error } = useAlert()
  const user = ref<any>(null)
  const showDeleteModal = ref(false)
  const confirmEmail = ref('')
  const deleteLoading = ref(false)

  const formatPhone = (phone: string) => {
    if (!phone) return ''
    const digits = phone.replace(/\D/g, '')
    if (digits.length === 11) {
      return digits.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3')
    }
    if (digits.length === 10) {
      return digits.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3')
    }
    return phone
  }

  const formattedPhone = computed(() => {
    if (!user.value?.telefone) return ''
    return formatPhone(user.value.telefone)
  })

  const getNivelIngles = (nivel: string) => {
    const niveis: Record<string, string> = {
      iniciante: 'Iniciante',
      intermediario: 'Intermediário',
      avancado: 'Avançado',
    }
    return niveis[nivel] || nivel || 'Não informado'
  }

  const getIdiomaPreferido = (idioma: string) => {
    const idiomas: Record<string, string> = {
      'pt-BR': 'Português (Brasil)',
      en: 'Inglês',
      es: 'Espanhol',
    }
    return idiomas[idioma] || idioma || 'Não informado'
  }

  const handleDeleteAccount = async () => {
    if (confirmEmail.value !== user.value?.email) {
      error('E-mail não confere')
      return
    }
    deleteLoading.value = true
    try {
      const response = await api.delete(`/users/delete/${user.value.id}`)
      if (response.data.success) {
        localStorage.removeItem('user')
        localStorage.removeItem('access_token')
        localStorage.removeItem('refresh_token')
        success('Conta excluída com sucesso!')
        setTimeout(() => router.push('/register'), 2000)
      } else {
        error(response.data.message || 'Erro ao excluir conta')
        setTimeout(() => {
          showDeleteModal.value = false
          confirmEmail.value = ''
        }, 1500)
      }
    } catch (err: any) {
      console.error('Erro ao excluir:', err)
      error(err.response?.data?.message || 'Erro de conexão com o servidor')
      setTimeout(() => {
        showDeleteModal.value = false
        confirmEmail.value = ''
      }, 1500)
    } finally {
      deleteLoading.value = false
      showDeleteModal.value = false
      confirmEmail.value = ''
    }
  }

  const loadUserData = async () => {
    const userData = localStorage.getItem('user')
    if (!userData) {
      router.push('/login')
      return
    }

    let localUser
    try {
      localUser = JSON.parse(userData)
    } catch (e) {
      console.error('Erro ao fazer parse do userData:', e)
      localStorage.removeItem('user')
      router.push('/login')
      return
    }

    if (!localUser || !localUser.id) {
      console.error('Usuário inválido ou sem ID')
      router.push('/login')
      return
    }

    try {
      const response = await api.get(`/users/${localUser.id}`)
      if (response.data.success) {
        const freshUser = response.data.user || response.data.data
        if (freshUser && freshUser.id) {
          user.value = freshUser
          localStorage.setItem('user', JSON.stringify(freshUser))
        } else {
          user.value = localUser
        }
      } else {
        user.value = localUser
      }
    } catch (e) {
      console.error('Erro ao carregar usuário:', e)
      user.value = localUser
    }
    if (!user.value) router.push('/login')
  }

  const handleUserUpdated = (event: CustomEvent) => {
    if (event.detail) {
      user.value = event.detail
      localStorage.setItem('user', JSON.stringify(event.detail))
    } else {
      loadUserData()
    }
  }

  const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
      loadUserData()
    }
  }

  onMounted(() => {
    loadUserData()
    window.addEventListener('user-updated', handleUserUpdated as EventListener)
    document.addEventListener('visibilitychange', handleVisibilityChange)
  })

  onUnmounted(() => {
    window.removeEventListener('user-updated', handleUserUpdated as EventListener)
    document.removeEventListener('visibilitychange', handleVisibilityChange)
  })

  return {
    user,
    formattedPhone,
    showDeleteModal,
    confirmEmail,
    deleteLoading,
    getNivelIngles,
    getIdiomaPreferido,
    handleDeleteAccount,
  }
}

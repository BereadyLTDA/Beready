// src/modules/auth/views/useProfile.ts
import { ref, onMounted, computed, onUnmounted } from 'vue'
import { useAlert } from '@/shared/composables/useAlert'
import api, { getApiErrorMessage } from '@/core/services/api'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { formatPhone } from '@/shared/composables/usePhoneMask'
import { userLanguageLabel } from '@/locales'

export function useProfile() {
  const authStore = useAuthStore()
  const { success, error } = useAlert()
  const { t } = useI18n()
  const user = computed(() => authStore.user)
  const showDeleteModal = ref(false)
  const confirmEmail = ref('')
  const senhaExclusao = ref('')
  const exportando = ref(false)

  // LGPD: baixa um JSON com tudo o que o BeReady guarda sobre o aluno
  const exportarDados = async () => {
    exportando.value = true
    try {
      const { data } = await api.get('/users/me/exportar')
      const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' })
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = 'beready-meus-dados.json'
      link.click()
      URL.revokeObjectURL(url)
      success(t('profile.exportado'))
    } catch (err: unknown) {
      error(getApiErrorMessage(err) || t('errors.networkError'))
    } finally {
      exportando.value = false
    }
  }
  const deleteLoading = ref(false)

  const formattedPhone = computed(() => {
    if (!user.value?.telefone) return ''
    return formatPhone(user.value.telefone)
  })

  const getNivelIngles = (nivel?: string) => {
    const niveis: Record<string, string> = {
      iniciante: t('profile.nivelIniciante'),
      intermediario: t('profile.nivelIntermediario'),
      avancado: t('profile.nivelAvancado'),
    }
    return (nivel && niveis[nivel]) || nivel || t('profile.naoInformado')
  }

  const getIdiomaPreferido = (idioma?: string) =>
    userLanguageLabel(t, idioma) || idioma || t('profile.naoInformado')

  const handleDeleteAccount = async () => {
    if (confirmEmail.value !== user.value?.email) {
      error(t('profile.emailMismatch'))
      return
    }
    const currentUser = user.value
    if (!currentUser) return

    deleteLoading.value = true
    try {
      // A senha confirma que é o dono da conta (contas só com login social não têm senha)
      const response = await api.delete(`/users/delete/${currentUser.id}`, { data: { senha: senhaExclusao.value } })
      if (response.data.success) {
        success(t('profile.deleteSuccess'))
        // Encerra a sessão (limpa cookie e stores) e leva ao cadastro
        setTimeout(() => authStore.logout({ redirectTo: '/register' }), 2000)
      } else {
        error(response.data.message || t('profile.deleteError'))
        setTimeout(() => {
          showDeleteModal.value = false
          confirmEmail.value = ''
        }, 1500)
      }
    } catch (err: unknown) {
      console.error('Erro ao excluir:', err)
      error(getApiErrorMessage(err) || t('errors.networkError'))
      setTimeout(() => {
        showDeleteModal.value = false
        confirmEmail.value = ''
      }, 1500)
    } finally {
      deleteLoading.value = false
      showDeleteModal.value = false
      confirmEmail.value = ''
      senhaExclusao.value = ''
    }
  }

  // Atualiza o usuário do store com os dados mais recentes do servidor
  const loadUserData = async () => {
    if (!authStore.isAuthenticated) return
    try {
      await authStore.fetchMe()
    } catch (e) {
      console.error('Erro ao carregar usuário:', e)
    }
  }

  const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
      loadUserData()
    }
  }

  onMounted(() => {
    loadUserData()
    document.addEventListener('visibilitychange', handleVisibilityChange)
  })

  onUnmounted(() => {
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
    senhaExclusao,
    exportando,
    exportarDados,
  }
}

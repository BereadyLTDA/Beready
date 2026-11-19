import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAlert } from '@/shared/composables/useAlert'
import api, { getApiErrorMessage } from '@/core/services/api'
import { usePermissionStore } from '@/stores/permissionStore'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import { getNivelLabelKey } from '@/shared/utils/nivelDificuldade'
import { formatDate as formatLocaleDate } from '@/shared/utils/intl'

export function useQuizView() {
  const router = useRouter()
  const route = useRoute()
  const { error } = useAlert()
  const { t } = useI18n()
  const permissionStore = usePermissionStore()
  
  const quizId = ref<number | null>(null)
  const quiz = ref({
    id: null,
    usuario_id: null as number | null,
    titulo: '',
    descricao: '',
    nivel_dificuldade: '',
    total_questoes: 0,
    tempo_limite: null,
    publico: false,
    criado_em: null,
  })

  const loadQuiz = async () => {
    const id = route.params.id
    if (!id) {
      error(t('quizes.missingId'))
      router.push('/quizes')
      return
    }

    // Depois de um F5 as permissões ainda podem estar carregando: espera antes de checar
    await useAuthStore().waitUserContext()
    if (!permissionStore.canView('quizes')) {
      error(t('quizes.viewDenied'))
      router.push('/quizes')
      return
    }

    quizId.value = Number(id)

    try {
      const response = await api.get(`/quizes/${quizId.value}`)
      const data = response.data

      if (data.success) {
        quiz.value = data.data
      } else {
        error(data.message || t('quizes.errorLoadOne'))
        router.push('/quizes')
      }
    } catch (err) {
      console.error('Erro:', err)
      error(getApiErrorMessage(err) || t('errors.networkError'))
      router.push('/quizes')
    }
  }

  const getLevelText = (level: string) => t(getNivelLabelKey(level))

  const formatDate = (date: string | null) => {
    if (!date) return t('profile.naoInformado')
    return formatLocaleDate(date)
  }

  onMounted(() => {
    loadQuiz()
  })

  return {
    quiz,
    quizId,
    getLevelText,
    formatDate,
  }
}
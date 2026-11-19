import { ref, computed, onMounted } from 'vue'
import { progressoService } from '@/modules/progresso/services/progressoService'
import { formatTempoEstudo } from '@/shared/utils/formatTempoEstudo'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

interface StatsData {
  flashcardsCount: number
  acertoRate: number
  sequenciaAtual: number
  tempoEstudo: string
  progressoGeral: number
}

export function useDashboard() {
  const { t } = useI18n()
  const authStore = useAuthStore()
  const user = computed(() => authStore.user)
  const loading = ref(false)
  const stats = ref<StatsData>({
    flashcardsCount: 0,
    acertoRate: 0,
    sequenciaAtual: 0,
    tempoEstudo: formatTempoEstudo(0),
    progressoGeral: 0,
  })

  const userName = computed(() => {
    if (user.value?.nome) {
      return user.value.nome.split(' ')[0]
    }
    return t('common.usuario')
  })

  const motivationalMessage = computed(() => {
    const dias = stats.value.sequenciaAtual
    if (dias >= 7) {
      return t('dashboard.motivacional.alta', { dias })
    }
    if (dias >= 3) {
      return t('dashboard.motivacional.media', { dias })
    }
    return t('dashboard.motivacional.padrao')
  })

  const loadUserData = async (): Promise<void> => {
    const currentUser = user.value
    if (!currentUser) return

    loading.value = true
    try {
      // GET /progresso/usuario/{id} => { success, message, data: Progresso }
      const response = await progressoService.getByUsuario(currentUser.id)
      const data = response.data.data

      if (response.data.success && data) {
        stats.value.flashcardsCount = data.flashcards_concluidos ?? 0
        stats.value.sequenciaAtual = data.sequencia_atual ?? 0
        stats.value.tempoEstudo = formatTempoEstudo(data.tempo_total_estudo ?? 0)
        stats.value.acertoRate = data.taxa_acerto ?? 0
        stats.value.progressoGeral = Math.min(100, data.progresso_geral ?? 0)
      }
    } catch {
      // 401 já é tratado pelo interceptor do api (refresh/logout centralizado);
      // demais erros: o dashboard mantém os valores zerados
    } finally {
      loading.value = false
    }
  }


  onMounted(() => {
    loadUserData()
  })

  return {
    user,
    loading,
    userName,
    stats,
    motivationalMessage,
  }
}
import { ref, onMounted } from 'vue'
import { useAlert } from '@/shared/composables/useAlert'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { progressoService } from '../services/progressoService'
import type { components } from '@/core/types/api.gen'

type Historico = components['schemas']['ProgressoHistorico']
import { formatTempoEstudo } from '@/shared/utils/formatTempoEstudo'

export function useProgressoDashboard() {
  const authStore = useAuthStore()
  const { error } = useAlert()
  const { t } = useI18n()

  const loading = ref(false)
  const progresso = ref({
    vocabulario_aprendido: 0,
    flashcards_concluidos: 0,
    quizes_concluidos: 0,
    tempo_total_estudo: 0,
    sequencia_atual: 0,
    maior_sequencia: 0,
  })

  // Séries dos gráficos; se falhar, a página continua só com os números
  const historico = ref<Historico | null>(null)
  const loadHistorico = async () => {
    try {
      const { data } = await progressoService.historico()
      historico.value = data.data
    } catch {
      historico.value = null
    }
  }

  const loadProgresso = async () => {
    const userId = authStore.user?.id
    if (!userId) return

    loading.value = true
    try {
      const response = await progressoService.getByUsuario(userId)
      const data = response.data.data
      if (response.data.success && data) {
        progresso.value = {
          vocabulario_aprendido: data.vocabulario_aprendido ?? 0,
          flashcards_concluidos: data.flashcards_concluidos ?? 0,
          quizes_concluidos: data.quizes_concluidos ?? 0,
          tempo_total_estudo: data.tempo_total_estudo ?? 0,
          sequencia_atual: data.sequencia_atual ?? 0,
          maior_sequencia: data.maior_sequencia ?? 0,
        }
      }
    } catch {
      error(t('progresso.errorLoad'))
    } finally {
      loading.value = false
    }
  }

  onMounted(() => {
    loadProgresso()
    loadHistorico()
  })

  return {
    progresso,
    historico,
    loading,
    formatarTempo: formatTempoEstudo,
    loadProgresso,
  }
}

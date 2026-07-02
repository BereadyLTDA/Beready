import { ref } from 'vue'
import { progressoService } from '../services/progressoService'
import { useAlert } from '@/shared/composables/useAlert'

export function useProgresso() {
  const progresso = ref<any>(null)
  const loading = ref(false)
  const { success, error } = useAlert()

  const fetchProgresso = async (usuarioId: number) => {
    loading.value = true
    try {
      const response = await progressoService.getByUsuario(usuarioId)
      progresso.value = response.data.data
      return progresso.value
    } catch (err: any) {
      if (err.response?.status === 400) {
        progresso.value = {
          usuario_id: usuarioId,
          vocabulario_aprendido: 0,
          flashcards_concluidos: 0,
          quizes_concluidos: 0,
          tempo_total_estudo: 0,
          sequencia_atual: 0,
          maior_sequencia: 0,
        }
        return progresso.value
      }
      error(err.response?.data?.message || 'Erro ao carregar progresso')
      throw err
    } finally {
      loading.value = false
    }
  }

  const saveProgresso = async (data: any) => {
    loading.value = true
    try {
      const response = await progressoService.save(data)
      progresso.value = response.data.data
      success('Progresso salvo com sucesso!')
      return progresso.value
    } catch (err: any) {
      error(err.response?.data?.message || 'Erro ao salvar progresso')
      throw err
    } finally {
      loading.value = false
    }
  }

  return { progresso, loading, fetchProgresso, saveProgresso }
}

// frontend/src/modules/traducoes/composables/useTraducoes.ts
import { ref } from 'vue'
import { traducaoService } from '../services/traducaoService'
import type { Traducao, ApiResponse } from '@/core/types'
import { useAlert } from '@/shared/composables/useAlert'

export function useTraducoes() {
  const traducoes = ref<Traducao[]>([])
  const loading = ref(false)
  const { success, error } = useAlert()

  const fetchTraducoes = async (promptId: number): Promise<Traducao[]> => {
    loading.value = true
    try {
      const response = (await traducaoService.getByPrompt(promptId)) as ApiResponse<Traducao[]>
      traducoes.value = response.data || []
      return traducoes.value
    } catch (err) {
      const axiosError = err as {
        response?: { status?: number; data?: { message?: string } }
        message?: string
      }
      if (axiosError.response?.status !== 400) {
        error(
          axiosError.response?.data?.message || axiosError.message || 'Erro ao carregar traduções',
        )
      }
      throw err
    } finally {
      loading.value = false
    }
  }

  const createTraducao = async (data: Omit<Traducao, 'id' | 'criado_em'>): Promise<Traducao> => {
    loading.value = true
    try {
      const response = (await traducaoService.create(data)) as ApiResponse<Traducao>
      const newTraducao = response.data
      traducoes.value.unshift(newTraducao)
      success('Tradução criada com sucesso!')
      return newTraducao
    } catch (err) {
      const axiosError = err as { response?: { data?: { message?: string } }; message?: string }
      error(axiosError.response?.data?.message || axiosError.message || 'Erro ao criar tradução')
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateTraducao = async (
    id: number,
    data: Partial<Omit<Traducao, 'id' | 'criado_em'>>,
  ): Promise<Traducao> => {
    loading.value = true
    try {
      const response = (await traducaoService.update(id, data)) as ApiResponse<Traducao>
      if (response.success) {
        await fetchTraducoes(response.data.prompt_id)
        success('Tradução atualizada com sucesso!')
        return response.data
      }
      throw new Error(response.message)
    } catch (err) {
      const axiosError = err as { response?: { data?: { message?: string } }; message?: string }
      error(
        axiosError.response?.data?.message || axiosError.message || 'Erro ao atualizar tradução',
      )
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteTraducao = async (id: number): Promise<void> => {
    loading.value = true
    try {
      const response = (await traducaoService.delete(id)) as ApiResponse<null>
      if (response.success) {
        traducoes.value = traducoes.value.filter((t) => t.id !== id)
        success('Tradução excluída com sucesso!')
      } else {
        throw new Error(response.message)
      }
    } catch (err) {
      const axiosError = err as { response?: { data?: { message?: string } }; message?: string }
      error(axiosError.response?.data?.message || axiosError.message || 'Erro ao excluir tradução')
      throw err
    } finally {
      loading.value = false
    }
  }

  return { traducoes, loading, fetchTraducoes, createTraducao, updateTraducao, deleteTraducao }
}

// frontend/src/modules/frases/composables/useFrases.ts
import { ref } from 'vue'
import { fraseService } from '../services/fraseService'
import type { Frase, ApiResponse } from '@/core/types'

export function useFrases(promptId: number) {
  const frases = ref<Frase[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchFrases = async () => {
    loading.value = true
    error.value = null
    try {
      const response = (await fraseService.getByPrompt(promptId)) as ApiResponse<Frase[]>
      if (response.success) {
        frases.value = response.data || []
      } else {
        error.value = response.message || 'Erro ao carregar frases'
      }
    } catch (err) {
      const axiosError = err as { response?: { data?: { message?: string } }; message?: string }
      error.value = axiosError.response?.data?.message || axiosError.message || 'Erro na requisição'
    } finally {
      loading.value = false
    }
  }

  const createFrase = async (data: Omit<Frase, 'id' | 'criado_em'>) => {
    const response = (await fraseService.create(data)) as ApiResponse<Frase>
    if (response.success) {
      await fetchFrases()
      return response.data
    }
    throw new Error(response.message)
  }

  const updateFrase = async (id: number, data: Partial<Omit<Frase, 'id' | 'criado_em'>>) => {
    const response = (await fraseService.update(id, data)) as ApiResponse<Frase>
    if (response.success) {
      await fetchFrases()
      return response.data
    }
    throw new Error(response.message)
  }

  const deleteFrase = async (id: number) => {
    const response = (await fraseService.delete(id)) as ApiResponse<null>
    if (response.success) {
      await fetchFrases()
    } else {
      throw new Error(response.message)
    }
  }

  return { frases, loading, error, fetchFrases, createFrase, updateFrase, deleteFrase }
}

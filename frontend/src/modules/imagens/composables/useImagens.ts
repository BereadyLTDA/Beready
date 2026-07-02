// frontend/src/modules/imagens/composables/useImagens.ts
import { ref } from 'vue'
import { imagemService } from '../services/imagemService'
import type { Imagem, ApiResponse } from '@/core/types'
import { useAlert } from '@/shared/composables/useAlert'

export function useImagens() {
  const imagens = ref<Imagem[]>([])
  const loading = ref(false)
  const { success, error } = useAlert()

  const fetchImagens = async (promptId: number): Promise<Imagem[]> => {
    loading.value = true
    try {
      const response = (await imagemService.getByPrompt(promptId)) as ApiResponse<Imagem[]>
      imagens.value = response.data || []
      return imagens.value
    } catch (err) {
      const axiosError = err as {
        response?: { status?: number; data?: { message?: string } }
        message?: string
      }
      if (axiosError.response?.status !== 400) {
        error(
          axiosError.response?.data?.message || axiosError.message || 'Erro ao carregar imagens',
        )
      }
      throw err
    } finally {
      loading.value = false
    }
  }

  const createImagem = async (data: Omit<Imagem, 'id' | 'criado_em'>): Promise<Imagem> => {
    loading.value = true
    try {
      const response = (await imagemService.create(data)) as ApiResponse<Imagem>
      const newImagem = response.data
      imagens.value.unshift(newImagem)
      success('Imagem criada com sucesso!')
      return newImagem
    } catch (err) {
      const axiosError = err as { response?: { data?: { message?: string } }; message?: string }
      error(axiosError.response?.data?.message || axiosError.message || 'Erro ao criar imagem')
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateImagem = async (
    id: number,
    data: Partial<Omit<Imagem, 'id' | 'criado_em'>>,
  ): Promise<Imagem> => {
    loading.value = true
    try {
      const response = (await imagemService.update(id, data)) as ApiResponse<Imagem>
      if (response.success) {
        await fetchImagens(response.data.prompt_id)
        success('Imagem atualizada com sucesso!')
        return response.data
      }
      throw new Error(response.message)
    } catch (err) {
      const axiosError = err as { response?: { data?: { message?: string } }; message?: string }
      error(axiosError.response?.data?.message || axiosError.message || 'Erro ao atualizar imagem')
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteImagem = async (id: number): Promise<void> => {
    loading.value = true
    try {
      const response = (await imagemService.delete(id)) as ApiResponse<null>
      if (response.success) {
        imagens.value = imagens.value.filter((i) => i.id !== id)
        success('Imagem excluída com sucesso!')
      } else {
        throw new Error(response.message)
      }
    } catch (err) {
      const axiosError = err as { response?: { data?: { message?: string } }; message?: string }
      error(axiosError.response?.data?.message || axiosError.message || 'Erro ao excluir imagem')
      throw err
    } finally {
      loading.value = false
    }
  }

  return { imagens, loading, fetchImagens, createImagem, updateImagem, deleteImagem }
}

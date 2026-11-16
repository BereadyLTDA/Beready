import { ref } from 'vue'
import axios from 'axios'
import { traducaoService } from '../services/traducaoService'
import type { Traducao } from '@/core/types'
import { getApiErrorMessage } from '@/core/services/api'
import { useAlert } from '@/shared/composables/useAlert'
import { useI18n } from 'vue-i18n'

type TraducaoInput = Omit<Traducao, 'id' | 'criado_em'>

/**
 * CRUD de traduções. Este composable já exibe os alertas de sucesso/erro;
 * as views não devem repeti-los (apenas tratar o fluxo).
 */
export function useTraducoes() {
  const traducoes = ref<Traducao[]>([])
  const loading = ref(false)
  const { success, error } = useAlert()
  const { t } = useI18n()

  const fail = (err: unknown, fallback: string): never => {
    error(getApiErrorMessage(err) || (err instanceof Error && err.message) || fallback)
    throw err
  }

  const fetchTraducoes = async (promptId: number): Promise<Traducao[]> => {
    loading.value = true
    try {
      const response = await traducaoService.getByPrompt(promptId)
      traducoes.value = response.data || []
      return traducoes.value
    } catch (err) {
      traducoes.value = []
      // 400 = prompt sem traduções (não é erro para o usuário)
      if (axios.isAxiosError(err) && err.response?.status === 400) return traducoes.value
      return fail(err, t('traducoes.errorLoad'))
    } finally {
      loading.value = false
    }
  }

  const createTraducao = async (data: TraducaoInput): Promise<Traducao> => {
    loading.value = true
    try {
      const response = await traducaoService.create(data)
      if (!response.success) throw new Error(response.message)
      traducoes.value.unshift(response.data)
      success(t('traducoes.successCreate'))
      return response.data
    } catch (err) {
      return fail(err, t('traducoes.errorCreate'))
    } finally {
      loading.value = false
    }
  }

  // Não mexe em `loading` (que troca a lista pelo spinner): quem chama controla o próprio estado
  const gerarTraducao = async (promptId: number, idiomaDestino: string): Promise<Traducao> => {
    try {
      const response = await traducaoService.gerar(promptId, idiomaDestino)
      if (!response.success) throw new Error(response.message)
      traducoes.value.unshift(response.data)
      success(t('traducoes.successGenerate'))
      return response.data
    } catch (err) {
      return fail(err, t('traducoes.errorGenerate'))
    }
  }

  const updateTraducao = async (id: number, data: Partial<TraducaoInput>): Promise<Traducao> => {
    loading.value = true
    try {
      const response = await traducaoService.update(id, data)
      if (!response.success) throw new Error(response.message)
      const index = traducoes.value.findIndex((item) => item.id === id)
      if (index !== -1) traducoes.value[index] = response.data
      success(t('traducoes.successUpdate'))
      return response.data
    } catch (err) {
      return fail(err, t('traducoes.errorUpdate'))
    } finally {
      loading.value = false
    }
  }

  const deleteTraducao = async (id: number): Promise<void> => {
    loading.value = true
    try {
      const response = await traducaoService.delete(id)
      if (!response.success) throw new Error(response.message)
      traducoes.value = traducoes.value.filter((item) => item.id !== id)
      success(t('traducoes.successDelete'))
    } catch (err) {
      fail(err, t('traducoes.errorDelete'))
    } finally {
      loading.value = false
    }
  }

  return {
    traducoes,
    loading,
    fetchTraducoes,
    createTraducao,
    gerarTraducao,
    updateTraducao,
    deleteTraducao,
  }
}

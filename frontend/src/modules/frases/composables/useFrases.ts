import { ref } from 'vue'
import axios from 'axios'
import { fraseService } from '../services/fraseService'
import type { Frase } from '@/core/types'
import { getApiErrorMessage } from '@/core/services/api'
import { useAlert } from '@/shared/composables/useAlert'
import { useI18n } from 'vue-i18n'

type FraseInput = Omit<Frase, 'id' | 'criado_em'>

/**
 * CRUD de frases semelhantes. Este composable já exibe os alertas de
 * sucesso/erro; as views não devem repeti-los (apenas tratar o fluxo).
 */
export function useFrases() {
  const frases = ref<Frase[]>([])
  const loading = ref(false)
  const { success, error } = useAlert()
  const { t } = useI18n()

  const fail = (err: unknown, fallback: string): never => {
    error(getApiErrorMessage(err) || (err instanceof Error && err.message) || fallback)
    throw err
  }

  const fetchFrases = async (promptId: number): Promise<Frase[]> => {
    loading.value = true
    try {
      const response = await fraseService.getByPrompt(promptId)
      frases.value = response.data || []
      return frases.value
    } catch (err) {
      frases.value = []
      // 400 = prompt sem frases (não é erro para o usuário)
      if (axios.isAxiosError(err) && err.response?.status === 400) return frases.value
      return fail(err, t('frases.errorLoad'))
    } finally {
      loading.value = false
    }
  }

  const createFrase = async (data: FraseInput): Promise<Frase> => {
    loading.value = true
    try {
      const response = await fraseService.create(data)
      if (!response.success) throw new Error(response.message)
      frases.value.unshift(response.data)
      success(t('success.created'))
      return response.data
    } catch (err) {
      return fail(err, t('frases.errorCreate'))
    } finally {
      loading.value = false
    }
  }

  // Não mexe em `loading` (que troca a lista pelo spinner): quem chama controla o próprio estado
  const gerarFrases = async (promptId: number, quantidade?: number): Promise<Frase[]> => {
    try {
      const response = await fraseService.gerar(promptId, quantidade)
      if (!response.success) throw new Error(response.message)
      frases.value.unshift(...response.data)
      success(t('frases.successGenerate'))
      return response.data
    } catch (err) {
      return fail(err, t('frases.errorGenerate'))
    }
  }

  const updateFrase = async (id: number, data: Partial<FraseInput>): Promise<Frase> => {
    loading.value = true
    try {
      const response = await fraseService.update(id, data)
      if (!response.success) throw new Error(response.message)
      const index = frases.value.findIndex((f) => f.id === id)
      if (index !== -1) frases.value[index] = response.data
      success(t('success.updated'))
      return response.data
    } catch (err) {
      return fail(err, t('frases.errorUpdate'))
    } finally {
      loading.value = false
    }
  }

  const deleteFrase = async (id: number): Promise<void> => {
    loading.value = true
    try {
      const response = await fraseService.delete(id)
      if (!response.success) throw new Error(response.message)
      frases.value = frases.value.filter((f) => f.id !== id)
      success(t('success.deleted'))
    } catch (err) {
      fail(err, t('frases.errorDelete'))
    } finally {
      loading.value = false
    }
  }

  return { frases, loading, fetchFrases, createFrase, gerarFrases, updateFrase, deleteFrase }
}

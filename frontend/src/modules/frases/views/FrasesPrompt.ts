import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useFrases } from '@/modules/frases/composables/useFrases'
import { promptService } from '@/modules/prompts/services/promptService'
import { useI18n } from 'vue-i18n'
import { useAlert } from '@/shared/composables/useAlert'
import { formatDate as formatLocaleDate } from '@/shared/utils/intl'
import { getNivelLabelKey } from '@/shared/utils/nivelDificuldade'
import type { Frase } from '@/core/types'

interface FraseForm {
  frase_semelhante: string
  pontuacao_semelhante: number
  tipo_frase: string
  nivel_dificuldade: string
}

// Tipo para o erro da API
interface ApiError {
  response?: {
    data?: {
      message?: string
    }
  }
  message?: string
}

export function useFrasesPrompt() {
  const route = useRoute()
  const { error } = useAlert()
  const { t, te } = useI18n()
  const promptId = ref<number>(0)
  const { frases, loading, fetchFrases, createFrase, gerarFrases, updateFrase, deleteFrase } = useFrases()
  const gerando = ref(false)

  // Gera a quantidade padrão do backend (5) a partir do texto do prompt
  const gerar = async (): Promise<void> => {
    gerando.value = true
    try {
      await gerarFrases(promptId.value)
    } catch {
      // Alerta de erro já exibido por useFrases
    } finally {
      gerando.value = false
    }
  }

  const promptTexto = ref<string>('')
  const modalOpen = ref(false)
  const saving = ref(false)
  const editingId = ref<number | null>(null)
  const deleting = ref(false)
  const confirmModalVisible = ref(false)
  const itemToDelete = ref<number | null>(null)

  const form = ref<FraseForm>({
    frase_semelhante: '',
    pontuacao_semelhante: 0.8,
    tipo_frase: 'alternativa',
    nivel_dificuldade: 'intermediario',
  })

  const loadPrompt = async () => {
    const idParam = route.params.promptId
    if (idParam) {
      promptId.value = Number(idParam)
      try {
        const response = await promptService.getById(promptId.value)
        if (response.data.data) {
          promptTexto.value = response.data.data.texto_original
        }
      } catch (err: unknown) {
        const apiError = err as ApiError
        error(apiError.response?.data?.message || t('prompts.errorLoadOne'))
      }
    }
  }

  const loadData = async () => {
    if (promptId.value) {
      await fetchFrases(promptId.value).catch(() => {
        // Alerta de erro já exibido por useFrases
      })
    }
  }

  const openModal = () => {
    editingId.value = null
    form.value = {
      frase_semelhante: '',
      pontuacao_semelhante: 0.8,
      tipo_frase: 'alternativa',
      nivel_dificuldade: 'intermediario',
    }
    modalOpen.value = true
  }

  const editFrase = (frase: Frase) => {
    editingId.value = frase.id
    form.value = {
      frase_semelhante: frase.frase_semelhante,
      pontuacao_semelhante: frase.pontuacao_semelhante || 0.8,
      tipo_frase: frase.tipo_frase || 'alternativa',
      nivel_dificuldade: frase.nivel_dificuldade || 'intermediario',
    }
    modalOpen.value = true
  }

  const closeModal = () => {
    modalOpen.value = false
  }

  const save = async () => {
    if (!form.value.frase_semelhante) {
      error(t('frases.fraseRequired'))
      return
    }
    saving.value = true
    try {
      if (editingId.value) {
        await updateFrase(editingId.value, {
          frase_semelhante: form.value.frase_semelhante,
          pontuacao_semelhante: form.value.pontuacao_semelhante,
          tipo_frase: form.value.tipo_frase,
          nivel_dificuldade: form.value.nivel_dificuldade,
        })
      } else {
        await createFrase({
          prompt_id: promptId.value,
          frase_semelhante: form.value.frase_semelhante,
          pontuacao_semelhante: form.value.pontuacao_semelhante,
          tipo_frase: form.value.tipo_frase,
          nivel_dificuldade: form.value.nivel_dificuldade,
        })
      }
      closeModal()
    } catch {
      // Alerta de erro já exibido por useFrases
    } finally {
      saving.value = false
    }
  }

  const confirmDelete = (frase: Frase) => {
    itemToDelete.value = frase.id
    confirmModalVisible.value = true
  }

  const handleConfirmDelete = async () => {
    if (!itemToDelete.value) return
    deleting.value = true
    try {
      await deleteFrase(itemToDelete.value)
    } catch {
      // Alerta de erro já exibido por useFrases
    } finally {
      deleting.value = false
      confirmModalVisible.value = false
      itemToDelete.value = null
    }
  }

  const formatDate = (date?: string): string => formatLocaleDate(date)

  // Rótulo traduzido do tipo da frase (o valor gravado no backend não muda)
  const getTipoLabel = (tipo?: string): string => {
    const key = `frases.tipos.${tipo || 'relacionada'}`
    return te(key) ? t(key) : tipo || ''
  }

  onMounted(async () => {
    await loadPrompt()
    await loadData()
  })

  return {
    promptId,
    promptTexto,
    loading,
    frases,
    gerando,
    gerar,
    modalOpen,
    saving,
    form,
    editingId,
    deleting,
    confirmModalVisible,
    formatDate,
    getTipoLabel,
    getNivelLabelKey,
    openModal,
    closeModal,
    editFrase,
    save,
    confirmDelete,
    handleConfirmDelete,
  }
}
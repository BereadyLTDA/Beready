import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useTraducoes } from '@/modules/traducoes/composables/useTraducoes'
import { promptService } from '@/modules/prompts/services/promptService'
import { useAlert } from '@/shared/composables/useAlert'
import type { Traducao } from '@/core/types'

interface TraducaoForm {
  texto_traduzido: string
  idioma_destino: string
  pontuacao_confianca: number
  servico_traducao: string
}

export function useTraducoesPrompt() {
  const route = useRoute()
  const { success, error } = useAlert()
  const { traducoes, loading, fetchTraducoes, createTraducao, updateTraducao, deleteTraducao } =
    useTraducoes()

  const promptId = ref<number>(0)
  const promptTexto = ref<string>('')
  const modalOpen = ref(false)
  const saving = ref(false)
  const editingId = ref<number | null>(null)
  const deleting = ref(false)
  const confirmModalVisible = ref(false)
  const itemToDelete = ref<number | null>(null)

  const form = ref<TraducaoForm>({
    texto_traduzido: '',
    idioma_destino: 'pt-BR',
    pontuacao_confianca: 0.95,
    servico_traducao: 'google',
  })

  const loadPrompt = async (): Promise<void> => {
    const idParam = route.params.promptId
    if (idParam) {
      promptId.value = Number(idParam)
      try {
        const response = await promptService.getById(promptId.value)
        if (response.data.data) {
          promptTexto.value = response.data.data.texto_original
        }
      } catch (err: unknown) {
        const axiosError = err as { response?: { data?: { message?: string } } }
        error(axiosError.response?.data?.message || 'Erro ao carregar prompt')
      }
    }
  }

  const loadData = async (): Promise<void> => {
    if (promptId.value) {
      await fetchTraducoes(promptId.value)
    }
  }

  const openModal = (): void => {
    editingId.value = null
    form.value = {
      texto_traduzido: '',
      idioma_destino: 'pt-BR',
      pontuacao_confianca: 0.95,
      servico_traducao: 'google',
    }
    modalOpen.value = true
  }

  const editTraducao = (traducao: Traducao): void => {
    editingId.value = traducao.id
    form.value = {
      texto_traduzido: traducao.texto_traduzido,
      idioma_destino: traducao.idioma_destino || 'pt-BR',
      pontuacao_confianca: traducao.pontuacao_confianca || 0.95,
      servico_traducao: traducao.servico_traducao || 'google',
    }
    modalOpen.value = true
  }

  const closeModal = (): void => {
    modalOpen.value = false
  }

  const save = async (): Promise<void> => {
    if (!form.value.texto_traduzido) {
      error('Texto traduzido é obrigatório')
      return
    }

    saving.value = true
    try {
      if (editingId.value) {
        await updateTraducao(editingId.value, {
          texto_traduzido: form.value.texto_traduzido,
          idioma_destino: form.value.idioma_destino,
          pontuacao_confianca: form.value.pontuacao_confianca,
          servico_traducao: form.value.servico_traducao,
        })
        success('Tradução atualizada com sucesso!')
      } else {
        await createTraducao({
          prompt_id: promptId.value,
          texto_traduzido: form.value.texto_traduzido,
          idioma_destino: form.value.idioma_destino,
          pontuacao_confianca: form.value.pontuacao_confianca,
          servico_traducao: form.value.servico_traducao,
        })
        success('Tradução criada com sucesso!')
      }
      await loadData()
      closeModal()
    } catch (err: unknown) {
      const axiosError = err as { response?: { data?: { message?: string } } }
      error(axiosError.response?.data?.message || 'Erro ao salvar tradução')
    } finally {
      saving.value = false
    }
  }

  const confirmDelete = (traducao: Traducao): void => {
    itemToDelete.value = traducao.id
    confirmModalVisible.value = true
  }

  const handleConfirmDelete = async (): Promise<void> => {
    if (!itemToDelete.value) return

    deleting.value = true
    try {
      await deleteTraducao(itemToDelete.value)
      success('Tradução excluída com sucesso!')
      await loadData()
    } finally {
      deleting.value = false
      confirmModalVisible.value = false
      itemToDelete.value = null
    }
  }

  const formatDate = (date?: string): string => {
    if (!date) return ''
    return new Date(date).toLocaleDateString('pt-BR')
  }

  onMounted(async () => {
    await loadPrompt()
    await loadData()
  })

  return {
    promptId,
    promptTexto,
    loading,
    traducoes,
    modalOpen,
    saving,
    form,
    editingId,
    deleting,
    confirmModalVisible,
    formatDate,
    openModal,
    closeModal,
    editTraducao,
    save,
    confirmDelete,
    handleConfirmDelete,
  }
}

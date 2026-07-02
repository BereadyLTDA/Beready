import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { promptService } from '@//modules/prompts/services/promptService'
import { useTraducoes } from '@/modules/traducoes/composables/useTraducoes.ts'
import { useImagens } from '@/modules/imagens/composables/useImagens'
import { useFrases } from '@/modules/frases/composables/useFrases'
import { useAlert } from '@/shared/composables/useAlert'
import type { Prompt, Traducao, Imagem, Frase } from '@/core/types'

type DeleteItemType = 'traducao' | 'imagem' | 'frase'

interface DeleteItem {
  type: DeleteItemType
  id: number
}

export function usePromptDetail() {
  const route = useRoute()
  const { error } = useAlert()
  const prompt = ref<Prompt | null>(null)
  const activeTab = ref<'traducoes' | 'imagens' | 'frases'>('traducoes')

  const { traducoes, fetchTraducoes, deleteTraducao } = useTraducoes()
  const { imagens, fetchImagens, deleteImagem } = useImagens()

  const loadingTraducoes = ref(false)
  const loadingImagens = ref(false)
  const loadingFrases = ref(false)

  const confirmModalVisible = ref(false)
  const confirmMessage = ref('')
  const itemToDelete = ref<DeleteItem | null>(null)
  const deleting = ref(false)

  const promptId = ref<number>(0)
  const { frases, fetchFrases, deleteFrase } = useFrases(promptId.value)

  const fetchPrompt = async (): Promise<void> => {
    const idParam = route.params.id
    if (idParam) {
      promptId.value = Number(idParam)
      try {
        const response = await promptService.getById(promptId.value)
        if (response.data.data) {
          prompt.value = response.data.data
        }
      } catch (err: unknown) {
        const axiosError = err as { response?: { data?: { message?: string } } }
        console.error('Erro ao carregar prompt:', axiosError.response?.data?.message)
        error(axiosError.response?.data?.message || 'Erro ao carregar prompt')
      }
    }
  }

  const loadTraducoes = async (): Promise<void> => {
    loadingTraducoes.value = true
    try {
      await fetchTraducoes(promptId.value)
    } catch (err: unknown) {
      const axiosError = err as { response?: { status?: number; data?: { message?: string } } }
      if (axiosError.response?.status === 400) {
        // Prompt não tem traduções ainda, ignora
        console.log('Nenhuma tradução encontrada para este prompt')
      } else {
        error(axiosError.response?.data?.message || 'Erro ao carregar traduções')
      }
    } finally {
      loadingTraducoes.value = false
    }
  }

  const loadImagens = async (): Promise<void> => {
    loadingImagens.value = true
    try {
      await fetchImagens(promptId.value)
    } catch (err: unknown) {
      const axiosError = err as { response?: { status?: number; data?: { message?: string } } }
      if (axiosError.response?.status === 400) {
        // Prompt não tem imagens ainda, ignora
        console.log('Nenhuma imagem encontrada para este prompt')
      } else {
        error(axiosError.response?.data?.message || 'Erro ao carregar imagens')
      }
    } finally {
      loadingImagens.value = false
    }
  }

  const loadFrases = async (): Promise<void> => {
    loadingFrases.value = true
    try {
      await fetchFrases()
    } catch (err: unknown) {
      const axiosError = err as { response?: { status?: number; data?: { message?: string } } }
      if (axiosError.response?.status === 400) {
        // Prompt não tem frases ainda, ignora
        console.log('Nenhuma frase encontrada para este prompt')
      } else {
        error(axiosError.response?.data?.message || 'Erro ao carregar frases')
      }
    } finally {
      loadingFrases.value = false
    }
  }

  const formatDate = (date?: string): string => {
    if (!date) return ''
    return new Date(date).toLocaleDateString('pt-BR')
  }

  const confirmDeleteTraducao = (traducao: Traducao): void => {
    itemToDelete.value = { type: 'traducao', id: traducao.id }
    confirmMessage.value = 'Tem certeza que deseja excluir esta tradução?'
    confirmModalVisible.value = true
  }

  const confirmDeleteImagem = (imagem: Imagem): void => {
    itemToDelete.value = { type: 'imagem', id: imagem.id }
    confirmMessage.value = 'Tem certeza que deseja excluir esta imagem?'
    confirmModalVisible.value = true
  }

  const confirmDeleteFrase = (frase: Frase): void => {
    itemToDelete.value = { type: 'frase', id: frase.id }
    confirmMessage.value = 'Tem certeza que deseja excluir esta frase?'
    confirmModalVisible.value = true
  }

  const handleConfirmDelete = async (): Promise<void> => {
    if (!itemToDelete.value) return

    deleting.value = true
    try {
      switch (itemToDelete.value.type) {
        case 'traducao':
          await deleteTraducao(itemToDelete.value.id)
          await loadTraducoes()
          break
        case 'imagem':
          await deleteImagem(itemToDelete.value.id)
          await loadImagens()
          break
        case 'frase':
          await deleteFrase(itemToDelete.value.id)
          await loadFrases()
          break
      }
    } catch (err: unknown) {
      const axiosError = err as { response?: { data?: { message?: string } } }
      error(axiosError.response?.data?.message || 'Erro ao excluir item')
    } finally {
      deleting.value = false
      confirmModalVisible.value = false
      itemToDelete.value = null
    }
  }

  onMounted(async () => {
    await fetchPrompt()
    await loadTraducoes()
    await loadImagens()
    await loadFrases()
  })

  return {
    prompt,
    activeTab,
    traducoes,
    imagens,
    frases,
    loadingTraducoes,
    loadingImagens,
    loadingFrases,
    confirmModalVisible,
    confirmMessage,
    deleting,
    formatDate,
    confirmDeleteTraducao,
    confirmDeleteImagem,
    confirmDeleteFrase,
    handleConfirmDelete,
  }
}

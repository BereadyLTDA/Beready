// src/modules/frases/views/FrasesPrompt.ts
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useFrases } from '@/modules/frases/composables/useFrases'
import { promptService } from '@/modules/prompts/services/promptService'
import { fraseService } from '@/modules/frases/services/fraseService'
import { useAlert } from '@/shared/composables/useAlert'
import type { Frase } from '@/core/types'

interface FraseForm {
  frase_semelhante: string
  pontuacao_semelhante: number
  tipo_frase: string
  nivel_dificuldade: string
}

export function useFrasesPrompt() {
  const route = useRoute()
  const { success, error } = useAlert()
  const promptId = ref<number>(0)
  const { frases, loading, fetchFrases, createFrase, deleteFrase } = useFrases(promptId.value)

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
      } catch (err: any) {
        error(err.response?.data?.message || 'Erro ao carregar prompt')
      }
    }
  }

  const loadData = async () => {
    if (promptId.value) {
      await fetchFrases()
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
      error('Frase semelhante é obrigatória')
      return
    }
    saving.value = true
    try {
      if (editingId.value) {
        await fraseService.update(editingId.value, {
          frase_semelhante: form.value.frase_semelhante,
          pontuacao_semelhante: form.value.pontuacao_semelhante,
          tipo_frase: form.value.tipo_frase,
          nivel_dificuldade: form.value.nivel_dificuldade,
        })
        success('Frase atualizada com sucesso!')
      } else {
        await createFrase({
          prompt_id: promptId.value,
          frase_semelhante: form.value.frase_semelhante,
          pontuacao_semelhante: form.value.pontuacao_semelhante,
          tipo_frase: form.value.tipo_frase,
          nivel_dificuldade: form.value.nivel_dificuldade,
        })
      }
      await loadData()
      closeModal()
    } catch (err: any) {
      error(err.response?.data?.message || 'Erro ao salvar frase')
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
    frases,
    modalOpen,
    saving,
    form,
    editingId,
    deleting,
    confirmModalVisible,
    formatDate,
    openModal,
    closeModal,
    editFrase,
    save,
    confirmDelete,
    handleConfirmDelete,
  }
}

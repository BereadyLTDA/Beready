import { ref, onMounted } from 'vue'
import { useAlert } from '@/shared/composables/useAlert'
import { tagService, type Tag } from '@/modules/tags/services/tagService'

export function useTags() {
  const { success, error } = useAlert()
  const tags = ref<Tag[]>([])
  const loading = ref(false)
  const saving = ref(false)
  const modalOpen = ref(false)
  const editingTag = ref<Tag | null>(null)

  const confirmModalVisible = ref(false)
  const tagToDelete = ref<Tag | null>(null)
  const deleting = ref(false)

  const form = ref({
    nome: '',
    cor: '#4CAF50',
    descricao: '',
  })

  const getCurrentUserId = (): number | null => {
    const userData = localStorage.getItem('user')
    if (!userData) return null
    try {
      const user = JSON.parse(userData)
      return user.id
    } catch {
      return null
    }
  }

  const fetchTags = async () => {
    const userId = getCurrentUserId()
    if (!userId) {
      tags.value = []
      return
    }

    loading.value = true
    try {
      const response = await tagService.getByUsuario(userId)
      tags.value = response.data.data || []
    } catch (err: any) {
      if (err.response?.status === 400) {
        tags.value = []
      } else {
        error(err.response?.data?.message || 'Erro ao carregar tags')
      }
    } finally {
      loading.value = false
    }
  }

  const openModal = () => {
    editingTag.value = null
    form.value = { nome: '', cor: '#4CAF50', descricao: '' }
    modalOpen.value = true
  }

  const editTag = (tag: Tag) => {
    editingTag.value = tag
    form.value = {
      nome: tag.nome,
      cor: tag.cor,
      descricao: tag.descricao || '',
    }
    modalOpen.value = true
  }

  const closeModal = () => {
    modalOpen.value = false
  }

  const saveTag = async () => {
    const userId = getCurrentUserId()
    if (!userId) {
      error('Usuário não autenticado')
      return
    }

    saving.value = true

    try {
      if (editingTag.value) {
        await tagService.update(editingTag.value.id!, form.value)
        success('Tag atualizada com sucesso!')
      } else {
        await tagService.create({ ...form.value, criado_por: userId })
        success('Tag criada com sucesso!')
      }
      await fetchTags()
      closeModal()
    } catch (err: any) {
      error(err.response?.data?.message || 'Erro ao salvar tag')
    } finally {
      saving.value = false
    }
  }

  const confirmDelete = (tag: Tag) => {
    tagToDelete.value = tag
    confirmModalVisible.value = true
  }

  const handleConfirmDelete = async () => {
    if (!tagToDelete.value) return

    deleting.value = true
    try {
      await tagService.delete(tagToDelete.value.id!)
      success('Tag excluída com sucesso!')
      await fetchTags()
    } catch (err: any) {
      error(err.response?.data?.message || 'Erro ao excluir tag')
    } finally {
      deleting.value = false
      confirmModalVisible.value = false
      tagToDelete.value = null
    }
  }

  onMounted(() => {
    fetchTags()
  })

  return {
    tags,
    loading,
    modalOpen,
    saving,
    form,
    editingTag,
    confirmModalVisible,
    tagToDelete,
    deleting,
    openModal,
    editTag,
    closeModal,
    saveTag,
    confirmDelete,
    handleConfirmDelete,
  }
}

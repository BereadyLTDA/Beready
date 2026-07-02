import { ref } from 'vue'
import { tagService, type Tag } from '@/modules/tags/services/tagService'
import { useAlert } from '@/shared/composables/useAlert'

export function useTags() {
  const tags = ref<Tag[]>([])
  const loading = ref(false)
  const { success, error } = useAlert()

  const fetchTags = async (usuarioId: number) => {
    loading.value = true
    try {
      const response = await tagService.getByUsuario(usuarioId)
      tags.value = response.data.data
      return tags.value
    } catch (err: any) {
      error(err.response?.data?.message || 'Erro ao carregar tags')
      throw err
    } finally {
      loading.value = false
    }
  }

  const createTag = async (tag: Tag) => {
    loading.value = true
    try {
      const response = await tagService.create(tag)
      tags.value.unshift(response.data.data)
      success('Tag criada com sucesso!')
      return response.data.data
    } catch (err: any) {
      error(err.response?.data?.message || 'Erro ao criar tag')
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateTag = async (id: number, data: Partial<Tag>) => {
    loading.value = true
    try {
      const response = await tagService.update(id, data)
      const index = tags.value.findIndex((t) => t.id === id)
      if (index !== -1) tags.value[index] = response.data.data
      success('Tag atualizada com sucesso!')
      return response.data.data
    } catch (err: any) {
      error(err.response?.data?.message || 'Erro ao atualizar tag')
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteTag = async (id: number) => {
    loading.value = true
    try {
      await tagService.delete(id)
      tags.value = tags.value.filter((t) => t.id !== id)
      success('Tag excluída com sucesso!')
    } catch (err: any) {
      error(err.response?.data?.message || 'Erro ao excluir tag')
      throw err
    } finally {
      loading.value = false
    }
  }

  return { tags, loading, fetchTags, createTag, updateTag, deleteTag }
}

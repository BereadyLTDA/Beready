// src/stores/tagStore.ts
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { tagService } from '@/modules/tags/services/tagService'
import type { Tag } from '@/core/types'

export const useTagStore = defineStore('tag', () => {
  const tags = ref<Tag[]>([])
  const loading = ref(false)

  const fetchTags = async () => {
    loading.value = true
    try {
      const response = await tagService.getAll()
      if (response.data.success) {
        tags.value = response.data.data
      }
    } finally {
      loading.value = false
    }
  }

  const createTag = async (tag: Omit<Tag, 'id' | 'criado_em'>) => {
    const response = await tagService.create(tag)
    if (response.data.success) {
      tags.value.push(response.data.data)
    }
    return response.data
  }

  const updateTag = async (id: number, data: Partial<Omit<Tag, 'id'>>) => {
    const response = await tagService.update(id, data)
    if (response.data.success) {
      const index = tags.value.findIndex((t) => t.id === id)
      if (index !== -1) tags.value[index] = { ...tags.value[index], ...(data as any) }
    }
    return response.data
  }

  const deleteTag = async (id: number) => {
    const response = await tagService.delete(id)
    if (response.data.success) {
      tags.value = tags.value.filter((t) => t.id !== id)
    }
    return response.data
  }

  return { tags, loading, fetchTags, createTag, updateTag, deleteTag }
})

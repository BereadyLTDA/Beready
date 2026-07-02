import api from '@/core/services/api'

export interface Tag {
  id?: number
  criado_por: number
  nome: string
  cor: string
  descricao?: string
  tag_sistema?: boolean
  criado_em?: string
}

export const tagService = {
  getAll: () => api.get('/tags'),
  getByUsuario: (usuarioId: number) => api.get(`/tags/usuario/${usuarioId}`),
  getById: (id: number) => api.get(`/tags/view/${id}`),
  create: (data: Tag) => api.post('/tags', data),
  update: (id: number, data: Partial<Tag>) => api.put(`/tags/edit/${id}`, data),
  delete: (id: number) => api.delete(`/tags/delete/${id}`),
}

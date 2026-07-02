import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/core/services/api'

export interface User {
  id: number
  uuid?: string
  nome: string
  email: string
  telefone?: string
  nivel_ingles?: string
  idioma_preferido?: string
  objetivos_aprendizado?: string
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('token'))

  const login = async (email: string, password: string) => {
    const response = await api.post('/auth/login', { email, password })
    if (response.data.success) {
      user.value = response.data.user
      localStorage.setItem('user', JSON.stringify(response.data.user))
      return true
    }
    throw new Error(response.data.message)
  }

  const register = async (data: any) => {
    const response = await api.post('/auth/register', data)
    if (response.data.success) {
      return response.data
    }
    throw new Error(response.data.message)
  }

  const logout = () => {
    user.value = null
    localStorage.removeItem('user')
  }

  const fetchUser = async (id: number) => {
    const response = await api.get(`/users/${id}`)
    if (response.data.success) {
      user.value = response.data.user
    }
  }

  return { user, login, register, logout, fetchUser }
})

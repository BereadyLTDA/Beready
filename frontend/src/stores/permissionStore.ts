// frontend/src/stores/permissionStore.ts

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/services/api'
import { useAuthStore } from '@/stores/auth'

// ============================================
// TYPES
// ============================================
export interface Role {
    id: number
    nome: string
    descricao: string
    nivel: number
    is_sistema: boolean
    is_ativo: boolean
    permissoes: Permission[]
}

export interface Permission {
    id: number
    nome: string
    descricao: string
    recurso: string
    acao: string
    is_ativo: boolean
}

export interface Plano {
    id: number
    nome: string
    descricao: string
    role_id: number | null
    preco_mensal: number
    preco_anual: number
    dias_trial: number
    recursos: string[]
    limites: Record<string, number>
    is_ativo: boolean
    ordem: number
    role?: Role
}

// Status da Subscription no Stripe (só nas assinaturas recorrentes); o acesso vem de status/data_fim
export type StripeStatus = 'active' | 'past_due' | 'unpaid' | 'canceled' | 'incomplete' | 'trialing' | 'paused'

export interface Assinatura {
    id: number
    usuario_id: number
    plano_id: number
    status: 'pending' | 'active' | 'canceled' | 'expired' | 'trial'
    data_inicio: string
    // null = sem vencimento (plano gratuito)
    data_fim: string | null
    data_cancelamento: string | null
    // Renovada automaticamente pelo Stripe (Premium recorrente)
    recorrente?: boolean
    stripe_status?: StripeStatus | null
    // Cancelamento agendado: não renova e o acesso vai até data_fim
    cancelar_no_fim_periodo?: boolean
    plano?: Plano
}

export interface AssinarPlanoResponse {
    success: boolean
    message?: string
    data?: {
        requires_payment: boolean
        checkout_url: string | null
        assinatura?: Assinatura
    }
}

// POST /planos/cancelar
export interface CancelarAssinaturaResponse {
    success: boolean
    message?: string
    data?: {
        assinatura: Assinatura | null
        // true = Premium recorrente: vale no fim do período (ativo_ate); false = encerrada na hora
        cancelamento_agendado: boolean
        ativo_ate: string | null
    }
}

// POST /planos/portal
export interface PortalResponse {
    success: boolean
    message?: string
    data?: { url: string }
}

// ============================================
// STORE
// ============================================
export const usePermissionStore = defineStore('permissions', () => {
    // State
    const roles = ref<Role[]>([])
    const permissions = ref<Permission[]>([])
    const planos = ref<Plano[]>([])
    const assinaturaAtiva = ref<Assinatura | null>(null)
    const userPermissions = ref<string[]>([])
    const loading = ref(false)

    // ============================================
    // GETTERS
    // ============================================
    // Admin = permissão admin.access OU role 'admin' vinda do servidor (store de auth)
    const isAdmin = computed((): boolean => {
        if (userPermissions.value.includes('admin.access')) return true
        return useAuthStore().isAdmin
    })

    const hasPermission = (permission: string): boolean => {
        if (isAdmin.value) return true
        return userPermissions.value.includes(permission)
    }

    const canView = (recurso: string): boolean => {
        if (isAdmin.value) return true
        return hasPermission(`${recurso}.view`)
    }

    const canCreate = (recurso: string): boolean => {
        if (isAdmin.value) return true
        return hasPermission(`${recurso}.create`)
    }

    const canEdit = (recurso: string): boolean => {
        if (isAdmin.value) return true
        return hasPermission(`${recurso}.edit`)
    }

    const canDelete = (recurso: string): boolean => {
        if (isAdmin.value) return true
        return hasPermission(`${recurso}.delete`)
    }

    const getPlanoAtual = computed((): Plano | null => {
        if (!assinaturaAtiva.value) return null
        return planos.value.find((p: Plano) => p.id === assinaturaAtiva.value?.plano_id) || null
    })

    // ============================================
    // ACTIONS
    // ============================================
    // Chamadas simultâneas esperam a mesma requisição (antes, a segunda voltava sem esperar e
    // a tela checava permissões ainda vazias)
    let permissionsPromise: Promise<void> | null = null
    const loadPermissions = (): Promise<void> => {
        permissionsPromise ??= carregarPermissoes().finally(() => {
            permissionsPromise = null
        })
        return permissionsPromise
    }

    const carregarPermissoes = async (): Promise<void> => {

        if (!useAuthStore().accessToken) {
            userPermissions.value = []
            return
        }

        loading.value = true
        try {
            const response = await api.get('/user/permissions')
            if (response.data.success) {
                userPermissions.value = response.data.data || []
            } else {
                userPermissions.value = []
            }
        } catch (error: unknown) {
            console.error('Erro ao carregar permissões:', error)
            userPermissions.value = []
        } finally {
            loading.value = false
        }
    }

    const loadRoles = async (): Promise<void> => {
        try {
            const response = await api.get('/admin/roles')
            if (response.data.success) {
                roles.value = response.data.data
            }
        } catch (error: unknown) {
            console.error('Erro ao carregar roles:', error)
        }
    }

    const loadPermissionsList = async (): Promise<void> => {
        try {
            const response = await api.get('/admin/permissions')
            if (response.data.success) {
                permissions.value = response.data.data
            }
        } catch (error: unknown) {
            console.error('Erro ao carregar permissões:', error)
        }
    }

    // O Postgres devolve colunas numeric como string ("29.90")
    const normalizePlano = (plano: Plano): Plano => ({
        ...plano,
        preco_mensal: Number(plano.preco_mensal),
        preco_anual: Number(plano.preco_anual),
        dias_trial: Number(plano.dias_trial)
    })

    // Admin: todos os planos (ativos e inativos)
    const loadPlanos = async (): Promise<void> => {
        try {
            const response = await api.get('/admin/planos')
            if (response.data.success) {
                planos.value = response.data.data.map(normalizePlano)
            }
        } catch (error: unknown) {
            console.error('Erro ao carregar planos:', error)
        }
    }

    // Público: apenas planos ativos, para a página de assinatura
    const loadPlanosAtivos = async (): Promise<void> => {
        try {
            const response = await api.get('/planos')
            if (response.data.success) {
                planos.value = response.data.data.map(normalizePlano)
            }
        } catch (error: unknown) {
            console.error('Erro ao carregar planos:', error)
        }
    }

    const loadAssinatura = async (): Promise<void> => {
        try {
            const response = await api.get('/user/assinatura')
            // Sem assinatura (ou resposta sem sucesso), não mantém a anterior na tela
            assinaturaAtiva.value = response.data.success ? response.data.data : null
        } catch (error: unknown) {
            assinaturaAtiva.value = null
            if (error && typeof error === 'object' && 'response' in error) {
                const err = error as { response?: { status?: number } }
                if (err.response?.status !== 404) {
                    console.error('Erro ao carregar assinatura:', error)
                }
            } else {
                console.error('Erro ao carregar assinatura:', error)
            }
        }
    }

    const createRole = async (data: Partial<Role>): Promise<unknown> => {
        try {
            const response = await api.post('/admin/roles', data)
            if (response.data.success) {
                await loadRoles()
            }
            return response.data
        } catch (error: unknown) {
            console.error('Erro ao criar role:', error)
            throw error
        }
    }

    const updateRole = async (roleId: number, data: Partial<Role>): Promise<unknown> => {
        try {
            const response = await api.put(`/admin/roles/${roleId}`, data)
            if (response.data.success) {
                await loadRoles()
            }
            return response.data
        } catch (error: unknown) {
            console.error('Erro ao atualizar role:', error)
            throw error
        }
    }

    // Rota para excluir função
    const deleteRole = async (roleId: number): Promise<unknown> => {
        try {
            const response = await api.delete(`/admin/roles/${roleId}`)
            if (response.data.success) {
                await loadRoles()
            }
            return response.data
        } catch (error: unknown) {
            console.error('Erro ao excluir role:', error)
            throw error
        }
    }

    const createPlano = async (data: Partial<Plano>): Promise<unknown> => {
        try {
            const response = await api.post('/admin/planos', data)
            if (response.data.success) {
                await loadPlanos()
            }
            return response.data
        } catch (error: unknown) {
            console.error('Erro ao criar plano:', error)
            throw error
        }
    }

    // Rota para atualizar plano
    const updatePlano = async (planoId: number, data: Partial<Plano>): Promise<unknown> => {
        try {
            const response = await api.put(`/admin/planos/edit/${planoId}`, data)
            if (response.data.success) {
                await loadPlanos()
            }
            return response.data
        } catch (error: unknown) {
            console.error('Erro ao atualizar plano:', error)
            throw error
        }
    }

    // Rotapara deletar plano
    const deletePlano = async (planoId: number): Promise<unknown> => {
        try {
            const response = await api.delete(`/admin/planos/delete/${planoId}`)
            if (response.data.success) {
                await loadPlanos()
            }
            return response.data
        } catch (error: unknown) {
            console.error('Erro ao excluir plano:', error)
            throw error
        }
    }

    const assinarPlano = async (planoId: number, ciclo: 'mensal' | 'anual' | 'trial'): Promise<AssinarPlanoResponse> => {
        try {
            const response = await api.post(`/planos/${planoId}/assinar`, { ciclo })
            if (response.data.success) {
                await loadAssinatura()
                await loadPermissions()
            }
            return response.data as AssinarPlanoResponse
        } catch (error: unknown) {
            console.error('Erro ao assinar plano:', error)
            throw error
        }
    }

    const cancelarAssinatura = async (): Promise<CancelarAssinaturaResponse> => {
        try {
            const response = await api.post('/planos/cancelar')
            if (response.data.success) {
                await loadAssinatura()
                await loadPermissions()
            }
            return response.data as CancelarAssinaturaResponse
        } catch (error: unknown) {
            console.error('Erro ao cancelar assinatura:', error)
            throw error
        }
    }

    // Billing Portal do Stripe (trocar cartão, ver faturas); devolve a URL para redirecionar
    const abrirPortalPagamento = async (): Promise<string> => {
        const response = await api.post('/planos/portal')
        const data = response.data as PortalResponse
        if (!data.success || !data.data?.url) {
            throw new Error(data.message || 'Portal indisponível')
        }
        return data.data.url
    }

    // Limpa todos os dados do usuário (chamado no logout centralizado)
    const reset = (): void => {
        roles.value = []
        permissions.value = []
        planos.value = []
        assinaturaAtiva.value = null
        userPermissions.value = []
        loading.value = false
    }

    // ============================================
    // RETURN
    // ============================================
    return {
        // State
        roles,
        permissions,
        planos,
        assinaturaAtiva,
        userPermissions,
        loading,

        // Getters
        isAdmin,
        hasPermission,
        canView,
        canCreate,
        canEdit,
        canDelete,
        getPlanoAtual,

        // Actions
        loadPermissions,
        loadRoles,
        loadPermissionsList,
        loadPlanos,
        loadAssinatura,
        loadPlanosAtivos,
        createRole,
        updateRole,
        deleteRole,
        createPlano,
        updatePlano,
        deletePlano,
        assinarPlano,
        cancelarAssinatura,
        abrirPortalPagamento,
        reset
    }
})
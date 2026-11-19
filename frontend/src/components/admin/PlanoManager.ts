// frontend/src/components/admin/PlanoManager.ts

import { ref, computed, watch, onMounted } from 'vue'
import { usePermissionStore, type Plano } from '@/stores/permissionStore'
import { useAlert } from '@/shared/composables/useAlert'
import { useI18n } from 'vue-i18n'
import { formatRecursoPlano, formatLimitePlano } from '@/shared/utils/planoLabels'
import { formatCurrency } from '@/shared/utils/intl'

export function usePlanoManager() {
    const permissionStore = usePermissionStore()
    const { success, error } = useAlert()
    const { t } = useI18n()

    const planosData = computed(() => permissionStore.planos)
    const rolesData = computed(() => permissionStore.roles)

    // Filtro de status
    const filterStatus = ref<'all' | 'active' | 'inactive'>('all')

    // Planos filtrados
    const filteredPlanos = computed(() => {
        const allPlans = planosData.value || []
        
        if (filterStatus.value === 'all') {
            return allPlans
        }
        
        const isActive = filterStatus.value === 'active'
        return allPlans.filter((plano: Plano) => {
            return Boolean(plano.is_ativo) === isActive
        })
    })

    const isModalOpen = ref(false)
    const editingPlano = ref<Plano | null>(null)
    const isSaving = ref(false)
    const recursosTextData = ref('')
    const limitesTextData = ref('')

    // Confirm Modal
    const confirmModalVisible = ref(false)
    const planoToDelete = ref<Plano | null>(null)
    const deleting = ref(false)

    // Toggle status loading
    const togglingStatus = ref<number | null>(null)

    const formData = ref({
        nome: '',
        descricao: '',
        role_id: null as number | null,
        preco_mensal: 0,
        preco_anual: 0,
        dias_trial: 0,
        recursos: [] as string[],
        limites: {} as Record<string, number>,
        ordem: 0,
        is_ativo: true
    })

    const resetForm = (): void => {
        editingPlano.value = null
        formData.value = {
            nome: '',
            descricao: '',
            role_id: null,
            preco_mensal: 0,
            preco_anual: 0,
            dias_trial: 0,
            recursos: [],
            limites: {},
            ordem: 0,
            is_ativo: true
        }
        recursosTextData.value = ''
        limitesTextData.value = ''
    }

    watch(editingPlano, (val) => {
        if (val) {
            recursosTextData.value = val.recursos?.join(', ') || ''
            limitesTextData.value = JSON.stringify(val.limites || {}, null, 2)
        }
    })

    const openCreateModal = (): void => {
        resetForm()
        isModalOpen.value = true
    }

    const handleEditPlano = (plano: Plano): void => {
        editingPlano.value = plano
        formData.value = {
            nome: plano.nome,
            descricao: plano.descricao || '',
            role_id: plano.role_id,
            preco_mensal: plano.preco_mensal,
            preco_anual: plano.preco_anual,
            dias_trial: plano.dias_trial || 0,
            recursos: plano.recursos || [],
            limites: plano.limites || {},
            ordem: plano.ordem || 0,
            is_ativo: plano.is_ativo
        }
        recursosTextData.value = plano.recursos?.join(', ') || ''
        limitesTextData.value = JSON.stringify(plano.limites || {}, null, 2)
        isModalOpen.value = true
    }

    const handleSavePlano = async (): Promise<void> => {
        isSaving.value = true
        try {
            formData.value.recursos = recursosTextData.value.split(',').map((s: string) => s.trim()).filter((s: string) => s)
            try {
                formData.value.limites = JSON.parse(limitesTextData.value)
            } catch {
                formData.value.limites = {}
            }

            const data = { ...formData.value }

            if (editingPlano.value) {
                await permissionStore.updatePlano(editingPlano.value.id, data)
                success(t('admin.planos.updateSuccess'))
            } else {
                await permissionStore.createPlano(data)
                success(t('admin.planos.createSuccess'))
            }

            handleCloseModal()
        } catch (err: unknown) {
            const errorMessage = err && typeof err === 'object' && 'response' in err
                ? (err as { response?: { data?: { message?: string } } }).response?.data?.message
                : t('admin.planos.errorSave')
            error(errorMessage || t('admin.planos.errorSave'))
        } finally {
            isSaving.value = false
        }
    }

    const handleToggleStatus = async (plano: Plano): Promise<void> => {
        togglingStatus.value = plano.id
        try {
            await permissionStore.updatePlano(plano.id, { is_ativo: !plano.is_ativo })
            success(t('success.statusUpdated'))
        } catch (err: unknown) {
            const errorMessage = err && typeof err === 'object' && 'response' in err
                ? (err as { response?: { data?: { message?: string } } }).response?.data?.message
                : t('admin.planos.errorStatus')
            error(errorMessage || t('admin.planos.errorStatus'))
        } finally {
            togglingStatus.value = null
        }
    }

    const confirmDelete = (plano: Plano): void => {
        planoToDelete.value = plano
        confirmModalVisible.value = true
    }

    const handleConfirmDelete = async (): Promise<void> => {
        if (!planoToDelete.value) return

        deleting.value = true
        try {
            await permissionStore.deletePlano(planoToDelete.value.id)
            success(t('admin.planos.deleteSuccess'))
            await permissionStore.loadPlanos()
        } catch (err: unknown) {
            const errorMessage = err && typeof err === 'object' && 'response' in err
                ? (err as { response?: { data?: { message?: string } } }).response?.data?.message
                : t('admin.planos.errorDelete')
            error(errorMessage || t('admin.planos.errorDelete'))
        } finally {
            deleting.value = false
            confirmModalVisible.value = false
            planoToDelete.value = null
        }
    }

    const handleCloseModal = (): void => {
        isModalOpen.value = false
        resetForm()
    }

    const formatRecurso = formatRecursoPlano
    const formatLimiteKey = formatLimitePlano

    // Carregar planos e roles ao inicializar
    onMounted(() => {
        permissionStore.loadPlanos()
        permissionStore.loadRoles()
    })

    return {
        planosData,
        filteredPlanos,
        filterStatus,
        rolesData,
        isModalOpen,
        editingPlano,
        isSaving,
        formData,
        recursosTextData,
        limitesTextData,
        confirmModalVisible,
        planoToDelete,
        deleting,
        togglingStatus,
        openCreateModal,
        handleEditPlano,
        handleSavePlano,
        handleToggleStatus,
        confirmDelete,
        handleConfirmDelete,
        handleCloseModal,
        formatRecurso,
        formatLimiteKey,
        formatCurrency
    }
}
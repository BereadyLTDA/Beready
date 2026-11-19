import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePermissionStore, type Plano } from '@/stores/permissionStore'
import { useAuthStore } from '@/stores/auth'
import { useAlert } from '@/shared/composables/useAlert'
import { useI18n } from 'vue-i18n'
import { getApiErrorMessage } from '@/core/services/api'
import { formatRecursoPlano, formatLimitePlano } from '@/shared/utils/planoLabels'
import { formatCurrency, formatDate } from '@/shared/utils/intl'

export function usePlanosPage() {
    const permissionStore = usePermissionStore()
    const authStore = useAuthStore()
    const route = useRoute()
    const router = useRouter()
    const { success, error, warning } = useAlert()
    const { t } = useI18n()
    const isLoading = ref(false)
    const isLoadingPlanos = ref(true)
    const showCancelModal = ref(false)

    const planosData = computed(() => permissionStore.planos)
    const planoAtual = computed(() => permissionStore.getPlanoAtual)
    const isPlanoAtual = (plano: Plano): boolean => planoAtual.value?.id === plano.id
    // Mesma regra do backend (AssinaturaService::isGratuito): só o plano gratuito não é cancelável
    // (e o Premium recorrente com cancelamento já agendado)
    const podeCancelar = computed(() => {
        const plano = planoAtual.value
        if (!plano || permissionStore.assinaturaAtiva?.cancelar_no_fim_periodo) return false
        return plano.preco_mensal > 0 || plano.preco_anual > 0 || plano.dias_trial > 0
    })

    const assinatura = computed(() => permissionStore.assinaturaAtiva)
    // Premium recorrente (Stripe): portal de pagamento e cancelamento no fim do período
    const isRecorrente = computed(() => Boolean(assinatura.value?.recorrente))
    const cancelamentoAgendado = computed(() => Boolean(assinatura.value?.cancelar_no_fim_periodo))
    const pagamentoPendente = computed(() =>
        isRecorrente.value && ['past_due', 'unpaid'].includes(assinatura.value?.stripe_status ?? ''),
    )
    const dataFimFormatada = computed(() => formatDate(assinatura.value?.data_fim))
    const isLoadingPortal = ref(false)

    // Texto de vigência do plano atual: renovação automática ou "ativo até"
    const vigenciaPlanoAtual = computed((): string => {
        const data = dataFimFormatada.value
        if (!data) return ''
        if (isRecorrente.value && !cancelamentoAgendado.value) return t('planos.renewsOn', { date: data })
        return t('planos.activeUntil', { date: data })
    })

    const cancelConfirmMessage = computed((): string =>
        isRecorrente.value && dataFimFormatada.value
            ? t('planos.cancelAtPeriodEndMessage', { date: dataFimFormatada.value })
            : t('planos.cancelConfirmMessage'),
    )

    const calcularEconomia = (plano: Plano): number => {
        if (plano.preco_mensal === 0 || plano.preco_anual === 0) return 0
        const anual = plano.preco_mensal * 12
        const economia = ((anual - plano.preco_anual) / anual) * 100
        return Math.round(economia)
    }

    const formatRecurso = formatRecursoPlano
    const formatLimiteKey = formatLimitePlano

    const isAuthenticated = computed(() => authStore.isAuthenticated)

    const handleAssinarPlano = async (plano: Plano, ciclo: 'mensal' | 'anual' = 'mensal'): Promise<void> => {
        if (isPlanoAtual(plano)) return

        // /planos é pública: sem sessão, assinar leva ao login em vez de um erro 401
        if (!isAuthenticated.value) {
            warning(t('planos.loginToSubscribe'))
            router.push('/login')
            return
        }

        isLoading.value = true
        try {
            const response = await permissionStore.assinarPlano(plano.id, ciclo)
            const checkoutUrl = response.data?.checkout_url

            if (response.data?.requires_payment && checkoutUrl) {
                window.location.href = checkoutUrl
            } else {
                success(t('planos.activatedSuccess', { nome: plano.nome }))
            }
        } catch (err: unknown) {
            error(getApiErrorMessage(err) || t('planos.errorSubscribe'))
        } finally {
            isLoading.value = false
        }
    }

    const handleCancelarAssinatura = async (): Promise<void> => {
        isLoading.value = true
        try {
            const response = await permissionStore.cancelarAssinatura()
            showCancelModal.value = false
            const ativoAte = response.data?.ativo_ate
            if (response.data?.cancelamento_agendado && ativoAte) {
                success(t('planos.cancelScheduledSuccess', { date: formatDate(ativoAte) }))
            } else {
                success(t('planos.canceledSuccess'))
            }
        } catch (err: unknown) {
            error(getApiErrorMessage(err) || t('planos.errorCancel'))
        } finally {
            isLoading.value = false
        }
    }

    const handleGerenciarPagamento = async (): Promise<void> => {
        isLoadingPortal.value = true
        try {
            window.location.href = await permissionStore.abrirPortalPagamento()
        } catch (err: unknown) {
            error(getApiErrorMessage(err) || t('planos.errorPortal'))
            isLoadingPortal.value = false
        }
    }

    onMounted(async () => {
        // Retorno do checkout do Stripe sem concluir o pagamento
        if (route.query.canceled === 'true') {
            warning(t('planos.paymentCanceled'))
            router.replace({ query: {} })
        }

        await Promise.all([
            permissionStore.loadPlanosAtivos(),
            authStore.isAuthenticated ? permissionStore.loadAssinatura() : Promise.resolve()
        ])
        isLoadingPlanos.value = false
    })

    return {
        planosData,
        isAuthenticated,
        isLoading,
        isLoadingPlanos,
        showCancelModal,
        podeCancelar,
        isRecorrente,
        cancelamentoAgendado,
        pagamentoPendente,
        vigenciaPlanoAtual,
        cancelConfirmMessage,
        isLoadingPortal,
        handleGerenciarPagamento,
        isPlanoAtual,
        calcularEconomia,
        formatRecurso,
        formatLimiteKey,
        formatCurrency,
        handleAssinarPlano,
        handleCancelarAssinatura
    }
}

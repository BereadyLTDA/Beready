import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/core/services/api'
import axios from 'axios'

export function useDashboard() {
  const router = useRouter()
  const user = ref<any>(null)
  const loading = ref(false)
  const stats = ref({
    flashcardsCount: 0,
    acertoRate: 0,
    sequenciaAtual: 0,
    tempoEstudo: '0h',
    progressoGeral: 0,
  })

  const userName = computed(() => {
    if (user.value?.nome) {
      return user.value.nome.split(' ')[0]
    }
    return 'Usuário'
  })

  const motivationalMessage = computed(() => {
    if (stats.value.sequenciaAtual >= 7) {
      return `🔥 Incrivel! ${stats.value.sequenciaAtual} dias de sequencia! Continue assim!`
    }
    if (stats.value.sequenciaAtual >= 3) {
      return `📈 ${stats.value.sequenciaAtual} dias seguidos! Voce esta evoluindo!`
    }
    return 'Continue sua jornada de aprendizado. Hoje é um otimo dia para aprender algo novo!'
  })

  const loadUserData = async () => {
    const userData = localStorage.getItem('user')
    if (!userData) return

    loading.value = true
    try {
      user.value = JSON.parse(userData)
      // Usa o token automaticamente via api (interceptor)
      const response = await api.get(`/progresso/usuario/${user.value.id}`)

      if (response.data.success && response.data.data) {
        const data = response.data.data

        stats.value.flashcardsCount = data.flashcards_concluidos || 0
        stats.value.sequenciaAtual = data.sequencia_atual || 0
        stats.value.tempoEstudo = `${Math.floor((data.tempo_total_estudo || 0) / 60)}h`

        // Ainda não calculados no backend — usa 0 até a feature existir.
        // Quando o backend passar a retornar taxa_acerto/progresso_geral,
        // isso vai funcionar automaticamente sem mudar nada aqui.
        stats.value.acertoRate = data.taxa_acerto ?? 0
        stats.value.progressoGeral = data.progresso_geral ?? 0
      }
    } catch (err: any) {
      console.error('Erro ao carregar estatisticas:', err)

      if (axios.isAxiosError(err) && err.response?.status === 401) {
        router.push('/login')
      }
    } finally {
      loading.value = false
    }
  }

  const handleLogout = () => {
    localStorage.removeItem('user')
    localStorage.removeItem('access_token')
    localStorage.removeItem('refresh_token')
    router.push('/login')
  }

  onMounted(() => {
    loadUserData()
  })

  return {
    user,
    loading,
    userName,
    stats,
    motivationalMessage,
    handleLogout,
  }
}
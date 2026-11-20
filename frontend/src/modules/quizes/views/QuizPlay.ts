import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { progressoService } from '@/modules/progresso/services/progressoService'
import { quizService } from '../services/quizService'
import { getApiErrorMessage } from '@/core/services/api'
import { useAlert } from '@/shared/composables/useAlert'
import { useConquistas } from '@/modules/conquistas/composables/useConquistas'
import { useAuthStore } from '@/stores/auth'
import { usePermissionStore } from '@/stores/permissionStore'
import type { Quiz, QuizCorrecao, QuizQuestao, QuizRespostaQuestao, QuizResultado } from '@/core/types'

export interface QuestaoRevisada {
  questao: QuizQuestao
  correcao: QuizCorrecao
  suaResposta: string
  respostaCorreta: string
}

/**
 * Jogo de quiz com as questões reais, uma por vez. O front não conhece o gabarito:
 * cada resposta é corrigida no servidor (POST .../verificar, sem gravar) para o feedback
 * imediato, e ao final POST /quizes/{id}/finalizar recalcula tudo, grava as respostas e
 * soma o quiz concluído no progresso.
 */
export function useQuizPlay() {
  const route = useRoute()
  const router = useRouter()
  const { t } = useI18n()
  const { checarNovas } = useConquistas()
  const { error } = useAlert()
  const authStore = useAuthStore()
  const permissionStore = usePermissionStore()

  const quiz = ref<Quiz | null>(null)
  const loading = ref(true)
  const currentIndex = ref(0)
  const alternativaSelecionada = ref<number | null>(null)
  const respostaTexto = ref('')
  const correcaoAtual = ref<QuizCorrecao | null>(null)
  const verificando = ref(false)
  const finalizando = ref(false)
  const respostas = ref<QuizRespostaQuestao[]>([])
  const resultado = ref<QuizResultado | null>(null)
  const sessionStartTime = ref(Date.now())

  const questoes = computed<QuizQuestao[]>(() => quiz.value?.questoes ?? [])
  const total = computed(() => questoes.value.length)
  const questaoAtual = computed<QuizQuestao | null>(() => questoes.value[currentIndex.value] ?? null)
  const isLast = computed(() => currentIndex.value >= total.value - 1)
  const isFinished = computed(() => resultado.value !== null)

  // Quiz sem questões: o dono (com permissão de editar) recebe o atalho para o editor
  const canAddQuestoes = computed(
    () => !!quiz.value && quiz.value.usuario_id === authStore.user?.id && permissionStore.canEdit('quizes'),
  )

  const podeResponder = computed(() => {
    if (!questaoAtual.value || correcaoAtual.value || verificando.value) return false
    return questaoAtual.value.tipo === 'completar'
      ? respostaTexto.value.trim() !== ''
      : alternativaSelecionada.value !== null
  })

  const focar = async (id: string) => {
    await nextTick()
    document.getElementById(id)?.focus()
  }

  const loadQuiz = async () => {
    const quizId = Number(route.params.id)
    loading.value = true
    try {
      await permissionStore.loadPermissions()
      const response = await quizService.getById(quizId)
      quiz.value = response.data.data
    } catch (err) {
      error(getApiErrorMessage(err) || t('quizes.errorLoad'))
      router.push('/quizes')
    } finally {
      loading.value = false
    }
  }

  const registrarTempo = async () => {
    const usuarioId = authStore.user?.id
    const segundos = Math.floor((Date.now() - sessionStartTime.value) / 1000)
    if (!usuarioId || segundos <= 0) return

    try {
      await progressoService.incrementarTempo(segundos, usuarioId)
    } catch {
      // Best-effort: não interrompe o jogo
    }
  }

  const responder = async () => {
    const questao = questaoAtual.value
    if (!quiz.value || !questao || !podeResponder.value) return

    const resposta: Omit<QuizRespostaQuestao, 'questao_id'> =
      questao.tipo === 'completar'
        ? { resposta: respostaTexto.value.trim() }
        : { alternativa_id: alternativaSelecionada.value ?? undefined }

    verificando.value = true
    try {
      const response = await quizService.verificar(quiz.value.id, questao.id, resposta)
      correcaoAtual.value = response.data.data
      respostas.value.push({ questao_id: questao.id, ...resposta })
      // O feedback é anunciado pelo alerta; o foco vai para "Próxima" (Enter continua)
      focar('quiz-play-next')
    } catch (err) {
      error(getApiErrorMessage(err) || t('quizPlay.errorCheck'))
    } finally {
      verificando.value = false
    }
  }

  const finalizar = async () => {
    if (!quiz.value) return

    finalizando.value = true
    try {
      const response = await quizService.finalizar(quiz.value.id, respostas.value)
      resultado.value = response.data.data
      await registrarTempo()
      checarNovas()
      focar('quiz-play-result')
    } catch (err) {
      error(getApiErrorMessage(err) || t('quizPlay.errorFinish'))
    } finally {
      finalizando.value = false
    }
  }

  const proxima = async () => {
    if (!correcaoAtual.value) return

    if (isLast.value) {
      await finalizar()
      return
    }

    currentIndex.value++
    alternativaSelecionada.value = null
    respostaTexto.value = ''
    correcaoAtual.value = null
    focar('quiz-play-question')
  }

  const textoAlternativa = (questao: QuizQuestao, id: number | null) =>
    questao.alternativas.find((alternativa) => alternativa.id === id)?.texto ?? ''

  // Revisão das erradas no resultado final
  const erradas = computed<QuestaoRevisada[]>(() => {
    if (!resultado.value) return []

    return resultado.value.correcao
      .filter((correcao) => !correcao.correta)
      .map((correcao) => {
        const questao = questoes.value.find((q) => q.id === correcao.questao_id)
        if (!questao) return null

        const suaResposta =
          questao.tipo === 'completar' ? correcao.resposta ?? '' : textoAlternativa(questao, correcao.alternativa_id)
        const respostaCorreta =
          questao.tipo === 'completar'
            ? correcao.resposta_esperada ?? ''
            : textoAlternativa(questao, correcao.alternativa_correta_id)

        return { questao, correcao, suaResposta, respostaCorreta }
      })
      .filter((item): item is QuestaoRevisada => item !== null)
  })

  const jogarNovamente = () => {
    currentIndex.value = 0
    alternativaSelecionada.value = null
    respostaTexto.value = ''
    correcaoAtual.value = null
    respostas.value = []
    resultado.value = null
    sessionStartTime.value = Date.now()
    focar('quiz-play-question')
  }

  const voltar = () => {
    router.push('/quizes')
  }

  onMounted(() => {
    sessionStartTime.value = Date.now()
    loadQuiz()
  })

  return {
    quiz,
    loading,
    total,
    currentIndex,
    questaoAtual,
    alternativaSelecionada,
    respostaTexto,
    correcaoAtual,
    verificando,
    finalizando,
    podeResponder,
    isLast,
    isFinished,
    resultado,
    erradas,
    canAddQuestoes,
    responder,
    proxima,
    jogarNovamente,
    voltar,
  }
}

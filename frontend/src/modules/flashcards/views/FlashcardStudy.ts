import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAlert } from '@/shared/composables/useAlert'
import { useConquistas } from '@/modules/conquistas/composables/useConquistas'
import { getApiErrorMessage } from '@/core/services/api'
import { progressoService } from '@/modules/progresso/services/progressoService'
import { flashcardService } from '../services/flashcardService'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import { getNivelLabelKey } from '@/shared/utils/nivelDificuldade'
import type { Flashcard, NotaRevisao } from '@/core/types'

interface StudyCard {
  id: number
  pergunta: string
  resposta: string
  nivel_dificuldade: string
  proxima_revisao?: string
}

export type StudyRating = 'hard' | 'good' | 'easy'

const NOTAS: Record<StudyRating, NotaRevisao> = {
  hard: 'errei',
  good: 'bom',
  easy: 'facil',
}

const toStudyCard = (item: Flashcard): StudyCard => ({
  id: item.id,
  pergunta: item.frente || '',
  resposta: item.verso || '',
  nivel_dificuldade: item.nivel_dificuldade || 'iniciante',
  proxima_revisao: item.proxima_revisao,
})

/**
 * Estudo de flashcards com repetição espaçada. Dois modos, mesma tela (virada 3D, teclado):
 *  - estudo (/flashcards/:id/study): começa pelo card escolhido e segue pela lista do usuário;
 *  - revisão (/flashcards/revisao): fila dos cards com revisão vencida, mostrando quantos faltam.
 * Cada avaliação (Errei/Bom/Fácil) é gravada em POST /flashcards/{id}/revisao, que reagenda o
 * card e registra resposta + progresso no backend.
 */
export function useFlashcardStudy() {
  const sessionStartTime = ref(Date.now())

  const router = useRouter()
  const route = useRoute()
  const authStore = useAuthStore()
  const { error } = useAlert()
  const { t } = useI18n()
  const { checarNovas } = useConquistas()

  const isReview = computed(() => route.name === 'flashcard-review')

  const flashcards = ref<StudyCard[]>([])
  const currentIndex = ref(0)
  const loading = ref(true)
  const saving = ref(false)
  const isFlipped = ref(false)
  const showCompletionModal = ref(false)
  const stats = ref({
    hard: 0,
    good: 0,
    easy: 0,
  })

  const allFlashcardIds = ref<number[]>([])
  const hasNextFlashcard = ref(false)

  const currentFlashcard = computed(() => flashcards.value[currentIndex.value])

  const flashcard = computed(() => currentFlashcard.value)

  // Modo revisão: posição na fila e quantos faltam (incluindo o atual)
  const reviewTotal = computed(() => flashcards.value.length)
  const reviewPosition = computed(() => Math.min(currentIndex.value + 1, reviewTotal.value))
  const remaining = computed(() => Math.max(reviewTotal.value - currentIndex.value, 0))

  const loadAllFlashcardIds = async () => {
    try {
      const { data } = await flashcardService.getAll()

      if (data.success && Array.isArray(data.data)) {
        allFlashcardIds.value = data.data.map((item) => Number(item.id))
        updateHasNextFlashcard()
      }
    } catch (err) {
      console.error('Erro ao carregar lista de flashcards:', err)
    }
  }

  const updateHasNextFlashcard = () => {
    const currentId = Number(route.params.id)
    const idx = allFlashcardIds.value.indexOf(currentId)

    hasNextFlashcard.value = idx !== -1 && idx < allFlashcardIds.value.length - 1
  }

  const loadFlashcard = async () => {
    const id = route.params.id

    if (!id) {
      error(t('flashcardStudy.missingId'))
      router.push('/flashcards')
      return
    }

    loading.value = true

    try {
      const { data } = await flashcardService.getById(Number(id))

      if (data.success) {
        flashcards.value = [toStudyCard(data.data)]
        currentIndex.value = 0
        updateHasNextFlashcard()
      } else {
        error(data.message || t('flashcardStudy.errorLoad'))
        router.push('/flashcards')
      }
    } catch (err) {
      console.error(err)
      error(getApiErrorMessage(err) || t('errors.networkError'))
      router.push('/flashcards')
    } finally {
      loading.value = false
    }
  }

  const loadReviewQueue = async () => {
    loading.value = true

    try {
      const { data } = await flashcardService.getDevidos()
      flashcards.value = (data.data?.flashcards ?? []).map(toStudyCard)
      currentIndex.value = 0
    } catch (err) {
      error(getApiErrorMessage(err) || t('revisao.errorLoad'))
      router.push('/flashcards')
    } finally {
      loading.value = false
    }
  }

  const load = () => (isReview.value ? loadReviewQueue() : loadFlashcard())

  const goBack = () => {
    router.push('/flashcards')
  }

  const flipCard = () => {
    isFlipped.value = !isFlipped.value
  }

  const goToNextFlashcard = () => {
    const currentId = Number(route.params.id)
    const idx = allFlashcardIds.value.indexOf(currentId)

    if (idx === -1 || idx >= allFlashcardIds.value.length - 1) {
      return
    }

    const nextId = allFlashcardIds.value[idx + 1]

    isFlipped.value = false

    router.push({
      name: route.name as string,
      params: { id: nextId },
    })
  }

  const incrementarTempo = async (usuarioId: number) => {
    const segundos = Math.floor((Date.now() - sessionStartTime.value) / 1000)

    if (segundos <= 0) return

    try {
      await progressoService.incrementarTempo(segundos, usuarioId)
    } catch (err) {
      console.error('Erro ao incrementar tempo de estudo:', err)
    }
  }

  const rateCard = async (rating: StudyRating) => {
    const card = currentFlashcard.value
    if (!card || saving.value) return

    // A avaliação precisa ser gravada para o agendamento; se falhar, o card fica para tentar de novo
    saving.value = true
    try {
      await flashcardService.revisar(card.id, NOTAS[rating])
    } catch (err) {
      error(getApiErrorMessage(err) || t('revisao.errorSave'))
      return
    } finally {
      saving.value = false
    }

    stats.value[rating]++

    if (isReview.value) {
      isFlipped.value = false
      currentIndex.value++
      if (currentIndex.value >= flashcards.value.length) {
        finishStudy()
      }
      return
    }

    if (hasNextFlashcard.value) {
      goToNextFlashcard()
    } else {
      finishStudy()
    }
  }

  const finishStudy = () => {
    checarNovas()
    const userId = authStore.user?.id

    if (userId) {
      incrementarTempo(userId)
    }

    showCompletionModal.value = true
  }

  const resetSession = () => {
    showCompletionModal.value = false
    stats.value = { hard: 0, good: 0, easy: 0 }
    isFlipped.value = false
    sessionStartTime.value = Date.now()
  }

  const studyAgain = () => {
    resetSession()

    if (isReview.value) {
      // Busca a fila de novo: os cards em que errou voltam em alguns minutos
      loadReviewQueue()
      return
    }

    if (allFlashcardIds.value.length > 0) {
      const firstId = allFlashcardIds.value[0]

      router.push({
        name: route.name as string,
        params: { id: firstId },
      })
    }
  }

  const getLevelText = (level: string) => t(getNivelLabelKey(level))

  // A mesma instância atende as duas rotas: troca de card (estudo) ou de modo (estudo <-> revisão)
  watch(
    () => [route.name, route.params.id] as const,
    ([name], [oldName]) => {
      if (name !== 'flashcard-study' && name !== 'flashcard-review') return
      if (name !== oldName) {
        resetSession()
        if (name === 'flashcard-study' && allFlashcardIds.value.length === 0) loadAllFlashcardIds()
      }
      load()
    },
  )

  onMounted(() => {
    sessionStartTime.value = Date.now()
    if (!isReview.value) loadAllFlashcardIds()
    load()
  })

  return {
    flashcard,
    flashcards,
    currentIndex,
    currentFlashcard,
    loading,
    saving,
    isFlipped,
    isReview,
    reviewTotal,
    reviewPosition,
    remaining,
    showCompletionModal,
    stats,
    hasNextFlashcard,
    goBack,
    flipCard,
    rateCard,
    finishStudy,
    studyAgain,
    getLevelText,
  }
}

import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useFlashcards } from '../composables/useFlashcards'
import type { Flashcard } from '@/core/types'
import { usePermissionStore } from '@/stores/permissionStore'
import { useAuthStore } from '@/stores/auth'
import { normalizeNivel, type NivelDificuldade } from '@/shared/utils/nivelDificuldade'

interface FormData {
  frente: string
  verso: string
  nivel_dificuldade: NivelDificuldade
}

export function useFlashcardsView() {
  const router = useRouter()
  const permissionStore = usePermissionStore()
  const authStore = useAuthStore()

  const {
    flashcards,
    loading,
    loadFlashcards,
    createFlashcard,
    updateFlashcard,
    deleteFlashcard,
    canCreateMore
  } = useFlashcards()

  const showModal = ref(false)
  const showDeleteModal = ref(false)
  const isEditing = ref(false)
  const editingId = ref<number | null>(null)
  const deletingFlashcard = ref<Flashcard | null>(null)
  const submitting = ref(false)
  const deleting = ref(false)

  const form = reactive<FormData>({
    frente: '',
    verso: '',
    nivel_dificuldade: 'iniciante',
  })

  const flashcardsList = computed<Flashcard[]>(() => flashcards.value)

  const flashcardsCount = computed(() => flashcards.value.length)

  // Computeds para o template - usando o permissionStore diretamente
  const canView = computed(() => permissionStore.canView('flashcards'))
  const canCreate = computed(() => permissionStore.canCreate('flashcards'))
  const canEdit = computed(() => permissionStore.canEdit('flashcards'))
  const canDelete = computed(() => permissionStore.canDelete('flashcards'))

  // Verifica se pode criar (permissão + limite)
  const canCreateFlashcard = computed(() => {
    return canCreate.value && canCreateMore()
  })

  const canCreateMoreFlashcards = computed(() => canCreateMore())

  const resetForm = (): void => {
    form.frente = ''
    form.verso = ''
    form.nivel_dificuldade = 'iniciante'
    editingId.value = null
    isEditing.value = false
  }

  const openCreateModal = (): void => {
    if (!canCreate.value) return
    if (!canCreateMore()) return
    resetForm()
    isEditing.value = false
    showModal.value = true
  }

  const openEditModal = (flashcard: Flashcard): void => {
    if (!canEdit.value) return
    form.frente = flashcard.frente
    form.verso = flashcard.verso
    form.nivel_dificuldade = normalizeNivel(flashcard.nivel_dificuldade)
    editingId.value = flashcard.id
    isEditing.value = true
    showModal.value = true
  }

  const viewFlashcard = (id: number): void => {
    if (!canView.value) return
    router.push(`/flashcards/${id}`)
  }

  const studyFlashcard = (id: number): void => {
    if (!canView.value) return
    router.push(`/flashcards/${id}/study`)
  }

  const confirmDelete = (flashcard: Flashcard): void => {
    if (!canDelete.value) return
    deletingFlashcard.value = flashcard
    showDeleteModal.value = true
  }

  const handleDelete = async (): Promise<void> => {
    if (!deletingFlashcard.value) return
    deleting.value = true
    try {
      await deleteFlashcard(deletingFlashcard.value.id)
      showDeleteModal.value = false
      await loadFlashcards()
    } catch {
      // Alerta de erro já exibido por useFlashcards
    } finally {
      deleting.value = false
      deletingFlashcard.value = null
    }
  }

  const submitForm = async (): Promise<void> => {
    const user = authStore.user
    if (!user) return
    // O "required" do navegador aceita só espaços; um card com frente ou verso em branco não serve
    if (!form.frente.trim() || !form.verso.trim()) return

    submitting.value = true

    try {
      const data = {
        usuario_id: user.id,
        frente: form.frente.trim(),
        verso: form.verso.trim(),
        nivel_dificuldade: form.nivel_dificuldade,
      }

      if (isEditing.value && editingId.value) {
        await updateFlashcard(editingId.value, data)
      } else {
        await createFlashcard(data)
      }

      closeModal()
      await loadFlashcards()
    } catch {
      // Alerta de erro já exibido por useFlashcards
    } finally {
      submitting.value = false
    }
  }

  const closeModal = (): void => {
    showModal.value = false
    resetForm()
  }

  onMounted(async () => {
    await permissionStore.loadPermissions()
    await loadFlashcards()
  })

  return {
    flashcards: flashcardsList,
    loading,
    showModal,
    showDeleteModal,
    isEditing,
    editingId,
    deletingFlashcard,
    submitting,
    deleting,
    form,
    flashcardsCount,
    canCreateFlashcard,
    canCreateMoreFlashcards,
    canView,
    canEdit,
    canDelete,
    openCreateModal,
    openEditModal,
    viewFlashcard,
    studyFlashcard,
    confirmDelete,
    handleDelete,
    submitForm,
    closeModal,
  }
}
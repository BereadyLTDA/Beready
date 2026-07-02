<template>
  <div class="flashcards-page">
    <div class="flashcards-hero">
      <button class="hero-back-btn" @click="$router.push('/dashboard')">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-5 w-5"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M10 19l-7-7m0 0l7-7m-7 7h18"
          />
        </svg>
        Voltar
      </button>
      <div class="hero-content">
        <div class="hero-icon">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-10 w-10"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <rect x="2" y="4" width="20" height="16" rx="2" />
            <path d="M8 8h8M8 12h6" />
          </svg>
        </div>
        <h1 class="hero-title">Meus Flashcards</h1>
        <p class="hero-subtitle">Crie, estude e revise seus flashcards para aprender melhor</p>
        <button class="hero-btn" @click="openCreateModal">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 4v16m8-8H4"
            />
          </svg>
          Novo Flashcard
        </button>
      </div>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Carregando flashcards...</p>
    </div>

    <div v-else-if="flashcards.length === 0" class="empty-state">
      <div class="empty-icon">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-12 w-12"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <rect x="2" y="4" width="20" height="16" rx="2" />
          <path d="M8 8h8M8 12h6" />
        </svg>
      </div>
      <h2 class="empty-title">Nenhum flashcard ainda</h2>
      <p class="empty-description">Comece criando seu primeiro flashcard para estudar!</p>
      <button class="empty-btn" @click="openCreateModal">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-5 w-5"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 4v16m8-8H4"
          />
        </svg>
        Criar Primeiro Flashcard
      </button>
    </div>

    <div v-else class="flashcards-grid">
      <div v-for="flashcard in flashcards" :key="flashcard.id" class="flashcard-card">
        <div class="flashcard-card-actions">
          <button class="btn-edit" @click.stop="openEditModal(flashcard)">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
              />
            </svg>
          </button>
          <button class="btn-delete" @click.stop="confirmDelete(flashcard)">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
              />
            </svg>
          </button>
        </div>
        <div class="flashcard-content" @click="viewFlashcard(flashcard.id)">
          <div class="flashcard-question">
            <div class="question-label">PERGUNTA</div>
            <div class="question-text">{{ flashcard.frente }}</div>
          </div>
          <div class="flashcard-answer">
            <div class="answer-label">RESPOSTA</div>
            <div class="answer-text">{{ flashcard.verso }}</div>
          </div>
        </div>
        <div class="flashcard-footer">
          <button class="btn-study" @click.stop="studyFlashcard(flashcard.id)">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <circle cx="12" cy="12" r="10" />
              <path d="M12 8v4l3 3" />
            </svg>
            Estudar</button
          >c
        </div>
      </div>
    </div>

    <!-- Modal de Criar/Editar -->
    <div v-if="showModal" class="modal-overlay" @click="closeModal">
      <div class="modal-container" @click.stop>
        <div class="modal-header">
          <div>
            <h2 class="modal-title">{{ isEditing ? 'Editar Flashcard' : 'Novo Flashcard' }}</h2>
            <p class="modal-subtitle">
              {{ isEditing ? 'Atualize as informações do flashcard' : 'Preencha os dados abaixo' }}
            </p>
          </div>
          <button class="modal-close" @click="closeModal">×</button>
        </div>
        <form @submit.prevent="submitForm">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Pergunta (Frente)</label>
              <textarea
                v-model="form.frente"
                class="form-textarea"
                rows="3"
                required
                placeholder="Digite a pergunta..."
              ></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Resposta (Verso)</label>
              <textarea
                v-model="form.verso"
                class="form-textarea"
                rows="3"
                required
                placeholder="Digite a resposta..."
              ></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Nível de Dificuldade</label>
              <select v-model="form.nivel_dificuldade" class="form-select">
                <option value="facil">Fácil</option>
                <option value="medio">Médio</option>
                <option value="dificil">Difícil</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-cancel" @click="closeModal">Cancelar</button>
            <button type="submit" class="btn-create" :disabled="submitting">
              {{ submitting ? 'Salvando...' : isEditing ? 'Atualizar' : 'Criar' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div v-if="showDeleteModal" class="modal-overlay" @click="showDeleteModal = false">
      <div class="modal-container confirm-modal" @click.stop>
        <div class="confirm-header">
          <div class="confirm-icon">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="35"
              height="35"
              viewBox="0 0 24 24"
              fill="none"
              stroke="white"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
          </div>
          <h2 class="modal-title">Excluir Flashcard?</h2>
        </div>
        <div class="confirm-body">
          <p>Tem certeza que deseja excluir o flashcard</p>
          <p class="flashcard-name">"{{ deletingFlashcard?.frente }}"</p>
          <p class="modal-warning">⚠️ Esta ação não pode ser desfeita!</p>
        </div>
        <div class="confirm-footer">
          <button class="btn-cancel" @click="showDeleteModal = false">Cancelar</button>
          <button class="btn-delete-confirm" @click="handleDelete" :disabled="deleting">
            {{ deleting ? 'Excluindo...' : 'Sim, excluir' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useFlashcards } from '../composables/useFlashcards'

const router = useRouter()
const { flashcards, loading, loadFlashcards, createFlashcard, updateFlashcard, deleteFlashcard } =
  useFlashcards()

const showModal = ref(false)
const showDeleteModal = ref(false)
const isEditing = ref(false)
const editingId = ref<number | null>(null)
const deletingFlashcard = ref<any>(null)
const submitting = ref(false)
const deleting = ref(false)

const form = reactive({
  frente: '',
  verso: '',
  nivel_dificuldade: 'medio',
})

const resetForm = () => {
  form.frente = ''
  form.verso = ''
  form.nivel_dificuldade = 'medio'
  editingId.value = null
  isEditing.value = false
}

const openCreateModal = () => {
  resetForm()
  isEditing.value = false
  showModal.value = true
}

const openEditModal = (flashcard: any) => {
  form.frente = flashcard.frente
  form.verso = flashcard.verso
  form.nivel_dificuldade = flashcard.nivel_dificuldade || 'medio'
  editingId.value = flashcard.id
  isEditing.value = true
  showModal.value = true
}

const viewFlashcard = (id: number) => {
  router.push(`/flashcards/${id}`)
}

const studyFlashcard = (id: number) => {
  router.push(`/flashcards/${id}/study`)
}

const confirmDelete = (flashcard: any) => {
  deletingFlashcard.value = flashcard
  showDeleteModal.value = true
}

const handleDelete = async () => {
  if (!deletingFlashcard.value) return
  deleting.value = true
  try {
    await deleteFlashcard(deletingFlashcard.value.id)
    showDeleteModal.value = false
    const userData = localStorage.getItem('user')
    if (userData) {
      const user = JSON.parse(userData)
      await loadFlashcards(user.id)
    }
  } finally {
    deleting.value = false
    deletingFlashcard.value = null
  }
}

const submitForm = async () => {
  const userData = localStorage.getItem('user')
  if (!userData) return

  let user
  try {
    user = JSON.parse(userData)
  } catch (e) {
    console.error('Erro ao fazer parse do userData:', e)
    return
  }

  submitting.value = true

  try {
    const data = {
      usuario_id: user.id,
      frente: form.frente,
      verso: form.verso,
      nivel_dificuldade: form.nivel_dificuldade,
    }

    if (isEditing.value && editingId.value) {
      await updateFlashcard(editingId.value, data)
    } else {
      await createFlashcard(data)
    }

    closeModal()
    await loadFlashcards(user.id)
  } finally {
    submitting.value = false
  }
}

const closeModal = () => {
  showModal.value = false
  resetForm()
}

onMounted(async () => {
  const userData = localStorage.getItem('user')
  if (userData) {
    try {
      const user = JSON.parse(userData)
      if (user && user.id) {
        await loadFlashcards(user.id)
      }
    } catch (e) {
      console.error('Erro ao fazer parse do userData:', e)
      localStorage.removeItem('user')
    }
  }
})
</script>

<style scoped>
@import '@/styles/views/flashcards/flashcards.css';
</style>

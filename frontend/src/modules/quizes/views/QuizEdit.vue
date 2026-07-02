<template>
  <div class="quiz-form-container">
    <div class="quiz-form-card">
      <div class="quiz-form-header">
        <div class="header-icon">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-8 w-8"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
            />
          </svg>
        </div>
        <h1 class="quiz-form-title">Editar Quiz</h1>
        <p class="quiz-form-subtitle">Atualize as informações do quiz</p>
      </div>

      <form @submit.prevent="handleSubmit">
        <div class="quiz-form-grid">
          <div class="form-group">
            <label class="form-label">Título *</label>
            <input v-model="form.titulo" type="text" class="form-input" />
            <span v-if="errors.titulo" class="form-error">{{ errors.titulo }}</span>
          </div>

          <div class="form-group">
            <label class="form-label">Nível de Dificuldade</label>
            <select v-model="form.nivel_dificuldade" class="form-input">
              <option value="iniciante">Iniciante</option>
              <option value="intermediario">Intermediário</option>
              <option value="avancado">Avançado</option>
            </select>
          </div>

          <div class="form-group full-width">
            <label class="form-label">Descrição</label>
            <textarea v-model="form.descricao" class="form-textarea" rows="4"></textarea>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Total de Questões</label>
              <input v-model.number="form.total_questoes" type="number" class="form-input" />
            </div>
            <div class="form-group">
              <label class="form-label">Tempo Limite (minutos)</label>
              <input v-model.number="form.tempo_limite" type="number" class="form-input" />
            </div>
          </div>

          <div class="form-group">
            <label class="form-checkbox">
              <input v-model="form.publico" type="checkbox" />
              <span> Público</span>
            </label>
          </div>
        </div>

        <div class="quiz-form-actions">
          <button type="button" class="btn-danger" @click="handleDelete">Excluir Quiz</button>
          <div>
            <button type="button" class="btn-cancel" @click="$router.push('/quizes')">
              Cancelar
            </button>
            <button type="submit" class="btn-submit" :disabled="loading">
              {{ loading ? 'Salvando...' : 'Salvar Alterações' }}
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div v-if="showDeleteModal" class="modal-overlay" @click.self="showDeleteModal = false">
      <div class="modal-container">
        <div class="modal-header">
          <div class="modal-icon danger">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-6 w-6"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
              />
            </svg>
          </div>
          <h3 class="modal-title">Excluir Quiz</h3>
        </div>
        <div class="modal-body">
          <p>
            Tem certeza que deseja excluir o quiz <strong>{{ form.titulo }}</strong
            >?
          </p>
          <p class="modal-warning">Esta ação é irreversível e todas as questões serão perdidas.</p>
        </div>
        <div class="modal-footer">
          <button @click="showDeleteModal = false" class="modal-btn-cancel">Cancelar</button>
          <button @click="confirmDelete" class="modal-btn-delete" :disabled="deleteLoading">
            {{ deleteLoading ? 'Excluindo...' : 'Sim, excluir' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useQuizEdit } from './QuizEdit'

const {
  form,
  errors,
  loading,
  deleteLoading,
  showDeleteModal,
  handleSubmit,
  handleDelete,
  confirmDelete,
} = useQuizEdit()
</script>

<style scoped>
@import '@/styles/views/quizes/quiz-form.css';
</style>

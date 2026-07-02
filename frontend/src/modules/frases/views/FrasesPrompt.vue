<template>
  <div class="frases-page">
    <div class="frases-hero">
      <button class="hero-back-btn" @click="$router.push(`/prompts/${promptId}`)">
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
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
            />
          </svg>
        </div>
        <h1 class="hero-title">Frases Semelhantes</h1>
        <p class="hero-subtitle">Prompt: {{ promptTexto }}</p>
      </div>
    </div>

    <div class="content-container">
      <div class="header-actions">
        <button class="btn-primary" @click="openModal">
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
          Nova Frase
        </button>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Carregando frases...</p>
      </div>

      <div v-else-if="frases.length === 0" class="empty-state">
        <div class="empty-icon">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-16 w-16"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
            />
          </svg>
        </div>
        <h2 class="empty-title">Nenhuma frase semelhante</h2>
        <p class="empty-description">Adicione frases semelhantes para este prompt</p>
        <button class="empty-btn" @click="openModal">Adicionar Frase</button>
      </div>

      <div v-else class="frases-grid">
        <div v-for="frase in frases" :key="frase.id" class="frase-card">
          <div class="card-header">
            <span class="badge" :class="frase.tipo_frase">{{
              frase.tipo_frase || 'relacionada'
            }}</span>
            <div class="card-actions">
              <button class="btn-icon edit" @click="editFrase(frase)">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                  />
                </svg>
              </button>
              <button class="btn-icon delete" @click="confirmDelete(frase)">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                  />
                </svg>
              </button>
            </div>
          </div>
          <p class="frase-text">{{ frase.frase_semelhante }}</p>
          <div class="card-footer">
            <span class="similarity"
              >Similaridade: {{ (frase.pontuacao_semelhante || 0) * 100 }}%</span
            >
            <span class="level">{{ frase.nivel_dificuldade || 'iniciante' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="modalOpen" class="modal-overlay" @click.self="closeModal">
      <div class="modal-container">
        <div class="modal-header">
          <h3 class="modal-title">{{ editingId ? 'Editar Frase' : 'Nova Frase' }}</h3>
          <button class="modal-close" @click="closeModal">×</button>
        </div>
        <form @submit.prevent="save">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Frase Semelhante *</label>
              <textarea
                v-model="form.frase_semelhante"
                rows="3"
                required
                class="form-textarea"
                placeholder="Digite uma frase semelhante..."
              ></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Similaridade (%)</label>
              <input
                type="range"
                v-model.number="form.pontuacao_semelhante"
                min="0"
                max="1"
                step="0.01"
                class="form-range"
              />
              <span class="range-value">{{ (form.pontuacao_semelhante || 0) * 100 }}%</span>
            </div>
            <div class="form-group">
              <label class="form-label">Tipo de Frase</label>
              <select v-model="form.tipo_frase" class="form-select">
                <option value="alternativa">Alternativa</option>
                <option value="sinonimo">Sinônimo</option>
                <option value="exemplo">Exemplo</option>
                <option value="relacionada">Relacionada</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Nível de Dificuldade</label>
              <select v-model="form.nivel_dificuldade" class="form-select">
                <option value="iniciante">Iniciante</option>
                <option value="intermediario">Intermediário</option>
                <option value="avancado">Avançado</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-cancel" @click="closeModal">Cancelar</button>
            <button type="submit" class="btn-save" :disabled="saving">
              {{ saving ? 'Salvando...' : 'Salvar' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Confirm Modal -->
    <ConfirmModal
      v-model="confirmModalVisible"
      title="Confirmar exclusão"
      message="Tem certeza que deseja excluir esta frase?"
      confirm-text="Excluir"
      type="danger"
      :loading="deleting"
      @confirm="handleConfirmDelete"
    />
  </div>
</template>

<script setup lang="ts">
import { useFrasesPrompt } from './FrasesPrompt'
import ConfirmModal from '@/shared/components/common/ConfirmModal.vue'

const {
  promptId,
  promptTexto,
  loading,
  frases,
  modalOpen,
  saving,
  form,
  editingId,
  deleting,
  confirmModalVisible,
  formatDate,
  openModal,
  closeModal,
  editFrase,
  save,
  confirmDelete,
  handleConfirmDelete,
} = useFrasesPrompt()
</script>

<style scoped>
@import '@/styles/views/frases/frases.css';
</style>

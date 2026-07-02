<template>
  <div class="prompts-page">
    <div class="prompts-hero">
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
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
            />
          </svg>
        </div>
        <h1 class="hero-title">Prompts com IA</h1>
        <p class="hero-subtitle">Gere prompts personalizados para praticar conversação</p>
        <button class="hero-btn" @click="openModal">
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
          Novo Prompt
        </button>
      </div>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Carregando seus prompts...</p>
    </div>

    <div v-else-if="prompts.length === 0" class="empty-state">
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
            d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
          />
        </svg>
      </div>
      <h2 class="empty-title">Nenhum prompt criado ainda</h2>
      <p class="empty-description">Crie seu primeiro prompt para gerar conteúdo com IA</p>
      <button class="empty-btn" @click="openModal">Criar primeiro prompt</button>
    </div>

    <div v-else class="prompts-grid">
      <div v-for="prompt in prompts" :key="prompt.id" class="prompt-card">
        <div class="prompt-header">
          <div class="prompt-badge">
            <span class="badge">{{ prompt.idioma_original?.toUpperCase() || 'PT' }}</span>
          </div>
          <div class="prompt-actions">
            <button class="btn-icon edit" @click="editPrompt(prompt)">
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
            <button class="btn-icon delete" @click="confirmDelete(prompt)">
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
        <div class="prompt-content">
          <p class="prompt-text">{{ prompt.texto_original }}</p>
          <div class="prompt-meta">
            <span class="meta-contexto">{{ prompt.contexto || 'manual' }}</span>
            <span class="meta-data">{{ formatDate(prompt.criado_em) }}</span>
          </div>
        </div>
        <div class="prompt-footer">
          <button class="btn-translate" @click="viewTranslations(prompt.id)">
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
                d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"
              />
            </svg>
            Ver Traduções
          </button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="modalOpen" class="modal-overlay" @click.self="closeModal">
      <div class="modal-container">
        <div class="modal-header">
          <h3 class="modal-title">{{ editingPrompt ? 'Editar Prompt' : 'Novo Prompt' }}</h3>
          <button class="modal-close" @click="closeModal">×</button>
        </div>
        <form @submit.prevent="savePrompt">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Texto Original *</label>
              <textarea
                v-model="form.texto_original"
                rows="4"
                required
                class="form-textarea"
                placeholder="Digite o texto em inglês ou outro idioma..."
              ></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Idioma Original</label>
              <select v-model="form.idioma_original" class="form-select">
                <option value="pt-BR">Português</option>
                <option value="en">Inglês</option>
                <option value="es">Espanhol</option>
                <option value="fr">Francês</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Contexto</label>
              <select v-model="form.contexto" class="form-select">
                <option value="manual">Manual</option>
                <option value="conversacao">Conversação</option>
                <option value="negocios">Negócios</option>
                <option value="viagem">Viagem</option>
                <option value="estudo">Estudo</option>
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
    <ConfirmModal
      v-model="confirmModalVisible"
      title="Confirmar exclusão"
      message="Tem certeza que deseja excluir este prompt?"
      :item-name="promptToDelete?.texto_original?.substring(0, 50)"
      confirm-text="Excluir"
      type="danger"
      :loading="deleting"
      @confirm="handleConfirmDelete"
    />
  </div>
</template>

<script setup lang="ts">
import { usePrompts } from './Prompts'
import ConfirmModal from '@/shared/components/common/ConfirmModal.vue'

const {
  loading,
  prompts,
  modalOpen,
  saving,
  form,
  editingPrompt,
  openModal,
  editPrompt,
  closeModal,
  savePrompt,
  confirmDelete,
  formatDate,
  viewTranslations,
  confirmModalVisible,
  promptToDelete,
  deleting,
  handleConfirmDelete,
} = usePrompts()
</script>

<style scoped>
@import '@/styles/views/prompts/prompts.css';
</style>

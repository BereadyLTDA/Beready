<template>
  <div class="traducoes-page">
    <div class="traducoes-hero">
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
              d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"
            />
          </svg>
        </div>
        <h1 class="hero-title">Traduções</h1>
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
          Nova Tradução
        </button>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Carregando traduções...</p>
      </div>

      <div v-else-if="traducoes.length === 0" class="empty-state">
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
              d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"
            />
          </svg>
        </div>
        <h2 class="empty-title">Nenhuma tradução ainda</h2>
        <p class="empty-description">Adicione sua primeira tradução para este prompt</p>
        <button class="empty-btn" @click="openModal">Adicionar Tradução</button>
      </div>

      <div v-else class="traducoes-grid">
        <div v-for="traducao in traducoes" :key="traducao.id" class="traducao-card">
          <div class="card-header">
            <span class="badge">{{ traducao.idioma_destino?.toUpperCase() || 'PT' }}</span>
            <div class="card-actions">
              <button class="btn-icon edit" @click="editTraducao(traducao)">
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
              <button class="btn-icon delete" @click="confirmDelete(traducao)">
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
          <p class="traducao-text">{{ traducao.texto_traduzido }}</p>
          <div class="card-footer">
            <span class="confidence"
              >Confiança: {{ (traducao.pontuacao_confianca || 0) * 100 }}%</span
            >
            <span class="date">{{ formatDate(traducao.criado_em) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="modalOpen" class="modal-overlay" @click.self="closeModal">
      <div class="modal-container">
        <div class="modal-header">
          <h3 class="modal-title">{{ editingId ? 'Editar Tradução' : 'Nova Tradução' }}</h3>
          <button class="modal-close" @click="closeModal">×</button>
        </div>
        <form @submit.prevent="save">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Texto Traduzido *</label>
              <textarea
                v-model="form.texto_traduzido"
                rows="4"
                required
                class="form-textarea"
                placeholder="Digite o texto traduzido..."
              ></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Idioma Destino</label>
              <select v-model="form.idioma_destino" class="form-select">
                <option value="pt-BR">Português (Brasil)</option>
                <option value="en">Inglês</option>
                <option value="es">Espanhol</option>
                <option value="fr">Francês</option>
                <option value="de">Alemão</option>
                <option value="it">Italiano</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Pontuação de Confiança</label>
              <input
                type="range"
                v-model.number="form.pontuacao_confianca"
                min="0"
                max="1"
                step="0.01"
                class="form-range"
              />
              <span class="range-value">{{ (form.pontuacao_confianca || 0) * 100 }}%</span>
            </div>
            <div class="form-group">
              <label class="form-label">Serviço de Tradução</label>
              <select v-model="form.servico_traducao" class="form-select">
                <option value="google">Google Translate</option>
                <option value="deepl">DeepL</option>
                <option value="microsoft">Microsoft Translator</option>
                <option value="openai">OpenAI</option>
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
      message="Tem certeza que deseja excluir esta tradução?"
      confirm-text="Excluir"
      type="danger"
      :loading="deleting"
      @confirm="handleConfirmDelete"
    />
  </div>
</template>

<script setup lang="ts">
import { useTraducoesPrompt } from './TraducoesPrompt'
import ConfirmModal from '@/shared/components/common/ConfirmModal.vue'

const {
  promptId,
  promptTexto,
  loading,
  traducoes,
  modalOpen,
  saving,
  form,
  editingId,
  deleting,
  confirmModalVisible,
  formatDate,
  openModal,
  closeModal,
  editTraducao,
  save,
  confirmDelete,
  handleConfirmDelete,
} = useTraducoesPrompt()
</script>

<style scoped>
@import '@/styles/views/traducoes/traducoes.css';
</style>

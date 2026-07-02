<template>
  <div class="imagens-page">
    <div class="imagens-hero">
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
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
          </svg>
        </div>
        <h1 class="hero-title">Imagens Geradas</h1>
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
          Nova Imagem
        </button>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Carregando imagens...</p>
      </div>

      <div v-else-if="imagens.length === 0" class="empty-state">
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
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
          </svg>
        </div>
        <h2 class="empty-title">Nenhuma imagem ainda</h2>
        <p class="empty-description">Gere sua primeira imagem para este prompt</p>
        <button class="empty-btn" @click="openModal">Adicionar Imagem</button>
      </div>

      <div v-else class="imagens-grid">
        <div v-for="imagem in imagens" :key="imagem.id" class="imagem-card">
          <div class="imagem-container">
            <img
              :src="imagem.url_imagem"
              :alt="imagem.prompt_imagem || 'Imagem gerada'"
              class="imagem-img"
            />
            <div class="imagem-overlay">
              <button class="btn-icon delete" @click="confirmDelete(imagem)">
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
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                  />
                </svg>
              </button>
            </div>
          </div>
          <div class="imagem-footer">
            <span class="servico">{{ imagem.servico_geracao || 'IA' }}</span>
            <span class="qualidade">{{ imagem.qualidade_imagem || 'media' }}</span>
            <span class="date">{{ formatDate(imagem.criado_em) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="modalOpen" class="modal-overlay" @click.self="closeModal">
      <div class="modal-container">
        <div class="modal-header">
          <h3 class="modal-title">{{ editingId ? 'Editar Imagem' : 'Nova Imagem' }}</h3>
          <button class="modal-close" @click="closeModal">×</button>
        </div>
        <form @submit.prevent="save">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">URL da Imagem *</label>
              <input
                v-model="form.url_imagem"
                type="url"
                required
                class="form-input"
                placeholder="https://exemplo.com/imagem.jpg"
              />
            </div>
            <div class="form-group">
              <label class="form-label">Prompt da Imagem</label>
              <textarea
                v-model="form.prompt_imagem"
                rows="3"
                class="form-textarea"
                placeholder="Descrição da imagem gerada..."
              ></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Serviço de Geração</label>
              <select v-model="form.servico_geracao" class="form-select">
                <option value="dalle">DALL-E</option>
                <option value="midjourney">Midjourney</option>
                <option value="stable">Stable Diffusion</option>
                <option value="other">Outro</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Qualidade</label>
              <select v-model="form.qualidade_imagem" class="form-select">
                <option value="baixa">Baixa</option>
                <option value="media">Média</option>
                <option value="alta">Alta</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Dimensões</label>
              <select v-model="form.dimensoes" class="form-select">
                <option value="512x512">512x512</option>
                <option value="1024x1024">1024x1024</option>
                <option value="1024x1792">1024x1792</option>
                <option value="1792x1024">1792x1024</option>
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
      message="Tem certeza que deseja excluir esta imagem?"
      confirm-text="Excluir"
      type="danger"
      :loading="deleting"
      @confirm="handleConfirmDelete"
    />
  </div>
</template>

<script setup lang="ts">
import { useImagensPrompt } from './ImagensPrompt'
import ConfirmModal from '@/shared/components/common/ConfirmModal.vue'

const {
  promptId,
  promptTexto,
  loading,
  imagens,
  modalOpen,
  saving,
  form,
  editingId,
  deleting,
  confirmModalVisible,
  formatDate,
  openModal,
  closeModal,
  save,
  confirmDelete,
  handleConfirmDelete,
} = useImagensPrompt()
</script>

<style scoped>
@import '@/styles/views/imagens/imagens.css';
</style>

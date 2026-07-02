<template>
  <div class="tags-page">
    <div class="tags-hero">
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
              d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-5-5A1.994 1.994 0 013 12V7a4 4 0 014-4z"
            />
          </svg>
        </div>
        <h1 class="hero-title">Minhas Tags</h1>
        <p class="hero-subtitle">Organize seus flashcards com tags personalizadas</p>
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
          Nova Tag
        </button>
      </div>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Carregando suas tags...</p>
    </div>

    <div v-else-if="tags.length === 0" class="empty-state">
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
            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-5-5A1.994 1.994 0 013 12V7a4 4 0 014-4z"
          />
        </svg>
      </div>
      <h2 class="empty-title">Nenhuma tag criada ainda</h2>
      <p class="empty-description">Crie sua primeira tag para organizar seus flashcards</p>
      <button class="empty-btn" @click="openModal">Criar primeira tag</button>
    </div>

    <div v-else class="tags-grid">
      <div v-for="tag in tags" :key="tag.id" class="tag-card" :style="{ borderLeftColor: tag.cor }">
        <div class="tag-info">
          <h3 class="tag-name">{{ tag.nome }}</h3>
          <p class="tag-description">{{ tag.descricao || 'Sem descrição' }}</p>
          <div class="tag-meta">
            <span class="tag-color" :style="{ backgroundColor: tag.cor }"></span>
          </div>
        </div>
        <div class="tag-actions">
          <button class="btn-icon edit" @click="editTag(tag)">
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
          <button class="btn-icon delete" @click="confirmDelete(tag)">
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
    </div>

    <!-- Modal -->
    <div v-if="modalOpen" class="modal-overlay" @click.self="closeModal">
      <div class="modal-container">
        <div class="modal-header">
          <h3 class="modal-title">{{ editingTag ? 'Editar Tag' : 'Nova Tag' }}</h3>
          <button class="modal-close" @click="closeModal">×</button>
        </div>
        <form @submit.prevent="saveTag">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Nome *</label>
              <input
                v-model="form.nome"
                type="text"
                required
                class="form-input"
                placeholder="Ex: Gramática"
              />
            </div>
            <div class="form-group">
              <label class="form-label">Cor</label>
              <input v-model="form.cor" type="color" class="form-color" />
            </div>
            <div class="form-group">
              <label class="form-label">Descrição</label>
              <textarea
                v-model="form.descricao"
                rows="3"
                class="form-textarea"
                placeholder="Descrição da tag..."
              ></textarea>
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
      message="Tem certeza que deseja excluir esta tag?"
      :item-name="tagToDelete?.nome"
      confirm-text="Excluir"
      type="danger"
      :loading="deleting"
      @confirm="handleConfirmDelete"
    />
  </div>
</template>

<script setup lang="ts">
import { useTags } from './Tags'
import ConfirmModal from '@/shared/components/common/ConfirmModal.vue'

const {
  tags,
  loading,
  modalOpen,
  saving,
  form,
  editingTag,
  confirmModalVisible,
  tagToDelete,
  deleting,
  openModal,
  editTag,
  closeModal,
  saveTag,
  confirmDelete,
  handleConfirmDelete,
} = useTags()
</script>

<style scoped>
@import '@/styles/views/tags/tags.css';
</style>

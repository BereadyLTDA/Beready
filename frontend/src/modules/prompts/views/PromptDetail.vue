<template>
  <div class="prompt-detail-page">
    <div class="prompt-detail-hero">
      <button class="hero-back-btn" @click="$router.push('/prompts')">
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
        <h1 class="hero-title">Detalhes do Prompt</h1>
        <p class="hero-subtitle">{{ prompt?.texto_original }}</p>
      </div>
    </div>

    <div class="tabs-container">
      <div class="tabs">
        <button
          class="tab-btn"
          :class="{ active: activeTab === 'traducoes' }"
          @click="activeTab = 'traducoes'"
        >
          Traduções ({{ traducoes.length }})
        </button>
        <button
          class="tab-btn"
          :class="{ active: activeTab === 'imagens' }"
          @click="activeTab = 'imagens'"
        >
          Imagens ({{ imagens.length }})
        </button>
        <button
          class="tab-btn"
          :class="{ active: activeTab === 'frases' }"
          @click="activeTab = 'frases'"
        >
          Frases Semelhantes ({{ frases.length }})
        </button>
      </div>

      <div class="tab-content">
        <!-- Traduções -->
        <div v-if="activeTab === 'traducoes'" class="tab-pane">
          <div class="section-header">
            <h2>Traduções</h2>
          </div>

          <div v-if="loadingTraducoes" class="loading-state">
            <div class="spinner"></div>
            <p>Carregando traduções...</p>
          </div>
          <div v-else-if="traducoes.length === 0" class="empty-state">
            <p>Nenhuma tradução disponível</p>
          </div>
          <div v-else class="traducoes-grid">
            <div v-for="traducao in traducoes" :key="traducao.id" class="traducao-card">
              <div class="traducao-header">
                <span class="badge">{{ traducao.idioma_destino?.toUpperCase() || 'PT' }}</span>
                <button class="btn-icon delete" @click="confirmDeleteTraducao(traducao)">
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
              <p class="traducao-text">{{ traducao.texto_traduzido }}</p>
              <div class="traducao-footer">
                <span class="confidence"
                  >Confiança: {{ (traducao.pontuacao_confianca || 0) * 100 }}%</span
                >
                <span class="date">{{ formatDate(traducao.criado_em) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Imagens -->
        <div v-if="activeTab === 'imagens'" class="tab-pane">
          <div class="section-header">
            <h2>Imagens Geradas</h2>
          </div>

          <div v-if="loadingImagens" class="loading-state">
            <div class="spinner"></div>
            <p>Carregando imagens...</p>
          </div>
          <div v-else-if="imagens.length === 0" class="empty-state">
            <p>Nenhuma imagem disponível</p>
          </div>
          <div v-else class="imagens-grid">
            <div v-for="imagem in imagens" :key="imagem.id" class="imagem-card">
              <img
                :src="imagem.url_imagem"
                :alt="imagem.prompt_imagem || 'Imagem gerada'"
                class="imagem-img"
              />
              <div class="imagem-overlay">
                <button class="btn-icon delete" @click="confirmDeleteImagem(imagem)">
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
              <div class="imagem-footer">
                <span class="servico">{{ imagem.servico_geracao || 'IA' }}</span>
                <span class="date">{{ formatDate(imagem.criado_em) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Frases Semelhantes -->
        <div v-if="activeTab === 'frases'" class="tab-pane">
          <div class="section-header">
            <h2>Frases Semelhantes</h2>
          </div>

          <div v-if="loadingFrases" class="loading-state">
            <div class="spinner"></div>
            <p>Carregando frases...</p>
          </div>
          <div v-else-if="frases.length === 0" class="empty-state">
            <p>Nenhuma frase semelhante disponível</p>
          </div>
          <div v-else class="frases-grid">
            <div v-for="frase in frases" :key="frase.id" class="frase-card">
              <div class="frase-header">
                <span class="badge">{{ frase.tipo_frase || 'relacionada' }}</span>
                <button class="btn-icon delete" @click="confirmDeleteFrase(frase)">
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
              <p class="frase-text">{{ frase.frase_semelhante }}</p>
              <div class="frase-footer">
                <span class="similarity"
                  >Similaridade: {{ (frase.pontuacao_semelhante || 0) * 100 }}%</span
                >
                <span class="level">{{ frase.nivel_dificuldade || 'iniciante' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirm Modal -->
    <ConfirmModal
      v-model="confirmModalVisible"
      title="Confirmar exclusão"
      :message="confirmMessage"
      confirm-text="Excluir"
      type="danger"
      :loading="deleting"
      @confirm="handleConfirmDelete"
    />
  </div>
</template>

<script setup lang="ts">
import { usePromptDetail } from './PromptDetail'
import ConfirmModal from '@/shared/components/common/ConfirmModal.vue'

const {
  prompt,
  activeTab,
  traducoes,
  imagens,
  frases,
  loadingTraducoes,
  loadingImagens,
  loadingFrases,
  confirmModalVisible,
  confirmMessage,
  deleting,
  formatDate,
  confirmDeleteTraducao,
  confirmDeleteImagem,
  confirmDeleteFrase,
  handleConfirmDelete,
} = usePromptDetail()
</script>

<style scoped>
@import '@/styles/views/prompts/prompt-detail.css';
</style>

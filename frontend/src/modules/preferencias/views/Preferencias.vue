<template>
  <div class="preferencias-page">
    <div class="preferencias-hero">
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
              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
            />
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
            />
          </svg>
        </div>
        <h1 class="hero-title">Preferências</h1>
        <p class="hero-subtitle">Personalize sua experiência no aplicativo</p>
      </div>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Carregando preferências...</p>
    </div>

    <form v-else @submit.prevent="handleSave" class="preferencias-form">
      <div class="form-group">
        <label class="form-label">Tema</label>
        <div class="theme-buttons">
          <button
            type="button"
            class="theme-btn"
            :class="{ active: form.tema === 'claro' }"
            @click="form.tema = 'claro'"
          >
            🌞 Claro
          </button>
          <button
            type="button"
            class="theme-btn"
            :class="{ active: form.tema === 'escuro' }"
            @click="form.tema = 'escuro'"
          >
            🌙 Escuro
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Modo Daltônico</label>
        <label class="toggle-switch">
          <input type="checkbox" v-model="form.modo_daltonico" />
          <span class="toggle-slider"></span>
        </label>
        <span class="helper-text">Adapta as cores para melhor visualização</span>
      </div>

      <div class="form-group">
        <label class="form-label">Notificações Ativas</label>
        <label class="toggle-switch">
          <input type="checkbox" v-model="form.notificacoes_ativas" />
          <span class="toggle-slider"></span>
        </label>
        <span class="helper-text">Receba lembretes e atualizações</span>
      </div>

      <div class="form-group">
        <label class="form-label">Som Ativo</label>
        <label class="toggle-switch">
          <input type="checkbox" v-model="form.som_ativo" />
          <span class="toggle-slider"></span>
        </label>
        <span class="helper-text">Efeitos sonoros durante os estudos</span>
      </div>

      <div class="form-group">
        <label class="form-label">Tradução Automática</label>
        <label class="toggle-switch">
          <input type="checkbox" v-model="form.traducao_automatica" />
          <span class="toggle-slider"></span>
        </label>
        <span class="helper-text">Traduza textos automaticamente</span>
      </div>

      <div class="form-group">
        <label class="form-label">Preferência de Dificuldade</label>
        <div class="difficulty-buttons">
          <button
            type="button"
            class="difficulty-btn"
            :class="{ active: form.preferencia_dificuldade === 'iniciante' }"
            @click="form.preferencia_dificuldade = 'iniciante'"
          >
            🌱 Iniciante
          </button>
          <button
            type="button"
            class="difficulty-btn"
            :class="{ active: form.preferencia_dificuldade === 'intermediario' }"
            @click="form.preferencia_dificuldade = 'intermediario'"
          >
            📚 Intermediário
          </button>
          <button
            type="button"
            class="difficulty-btn"
            :class="{ active: form.preferencia_dificuldade === 'avancado' }"
            @click="form.preferencia_dificuldade = 'avancado'"
          >
            🎓 Avançado
          </button>
          <button
            type="button"
            class="difficulty-btn"
            :class="{ active: form.preferencia_dificuldade === 'adaptativo' }"
            @click="form.preferencia_dificuldade = 'adaptativo'"
          >
            🤖 Adaptativo
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Meta Diária (minutos)</label>
        <div class="meta-input">
          <input
            type="range"
            v-model.number="form.meta_diaria_minutos"
            min="5"
            max="120"
            step="5"
            class="meta-range"
          />
          <div class="meta-value">
            <span class="meta-number">{{ form.meta_diaria_minutos }}</span>
            <span class="meta-unit">minutos</span>
          </div>
        </div>
        <div class="meta-labels">
          <span>5 min</span>
          <span>30 min</span>
          <span>60 min</span>
          <span>90 min</span>
          <span>120 min</span>
        </div>
      </div>

      <button type="submit" :disabled="saving" class="btn-save">
        {{ saving ? 'Salvando...' : 'Salvar Preferências' }}
      </button>
    </form>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { usePreferencias } from '../composables/usePreferencias'

const { form, loading, saving, fetchPreferencias, savePreferencias } = usePreferencias()

const handleSave = async () => {
  const userData = localStorage.getItem('user')
  if (userData) {
    const user = JSON.parse(userData)
    await savePreferencias(user.id)
  }
}

onMounted(async () => {
  const userData = localStorage.getItem('user')
  if (userData) {
    try {
      const user = JSON.parse(userData)
      if (user && user.id) {
        await fetchPreferencias(user.id)
      } else {
        console.error('Usuário inválido')
      }
    } catch (e) {
      console.error('Erro ao fazer parse do user:', e)
    }
  }
})
</script>

<style scoped>
@import '@/styles/views/preferencias/preferencias.css';
</style>

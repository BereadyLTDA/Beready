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
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
          </svg>
        </div>
        <h1 class="quiz-form-title">Criar Novo Quiz</h1>
        <p class="quiz-form-subtitle">Preencha os dados abaixo para criar um novo quiz</p>
      </div>

      <form @submit.prevent="handleSubmit">
        <div class="quiz-form-grid">
          <div class="form-group">
            <label class="form-label">Título *</label>
            <input
              v-model="form.titulo"
              type="text"
              class="form-input"
              placeholder="Ex: Vocabulário Básico"
            />
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
            <textarea
              v-model="form.descricao"
              class="form-textarea"
              rows="4"
              placeholder="Descreva o conteúdo do quiz..."
            ></textarea>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Total de Questões</label>
              <input
                v-model.number="form.total_questoes"
                type="number"
                class="form-input"
                placeholder="Ex: 10"
              />
            </div>
            <div class="form-group">
              <label class="form-label">Tempo Limite (minutos)</label>
              <input
                v-model.number="form.tempo_limite"
                type="number"
                class="form-input"
                placeholder="Opcional"
              />
            </div>
          </div>

          <div class="form-group">
            <label class="form-checkbox">
              <input v-model="form.publico" type="checkbox" />
              <span> Tornar público (outros usuários podem visualizar)</span>
            </label>
          </div>
        </div>

        <div class="quiz-form-actions">
          <button type="button" class="btn-cancel" @click="$router.push('/quizes')">
            Cancelar
          </button>
          <button type="submit" class="btn-submit" :disabled="loading">
            {{ loading ? 'Salvando...' : 'Criar Quiz' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useQuizAdd } from './QuizAdd'

const { form, errors, loading, handleSubmit } = useQuizAdd()
</script>

<style scoped>
@import '@/styles/views/quizes/quiz-form.css';
</style>

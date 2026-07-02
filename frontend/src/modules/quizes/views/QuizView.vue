<template>
  <div class="quiz-view-container">
    <div class="quiz-view-card">
      <div class="quiz-view-header">
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
              d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
            />
          </svg>
        </div>
        <h1 class="quiz-view-title">{{ quiz.titulo || 'Carregando...' }}</h1>
        <div class="quiz-view-badges">
          <span class="badge" :class="getLevelClass(quiz.nivel_dificuldade)">
            {{ getLevelText(quiz.nivel_dificuldade) }}
          </span>
          <span v-if="quiz.publico" class="badge badge-public">Público</span>
          <span v-else class="badge badge-private">Privado</span>
        </div>
      </div>

      <div class="quiz-view-body">
        <div class="info-section">
          <div class="info-card">
            <div class="info-icon">
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
                  d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
            <div class="info-content">
              <span class="info-label">Total de Questões</span>
              <span class="info-value">{{ quiz.total_questoes || 0 }}</span>
            </div>
          </div>
          <div class="info-card">
            <div class="info-icon">
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
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
            <div class="info-content">
              <span class="info-label">Tempo Limite</span>
              <span class="info-value">{{
                quiz.tempo_limite ? quiz.tempo_limite + ' minutos' : 'Sem limite'
              }}</span>
            </div>
          </div>
          <div class="info-card">
            <div class="info-icon">
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
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                />
              </svg>
            </div>
            <div class="info-content">
              <span class="info-label">Criado em</span>
              <span class="info-value">{{ formatDate(quiz.criado_em) }}</span>
            </div>
          </div>
        </div>

        <div class="description-section">
          <h3 class="section-title">Descrição</h3>
          <p class="description-text">{{ quiz.descricao || 'Nenhuma descrição fornecida.' }}</p>
        </div>

        <div class="quiz-view-actions">
          <button class="btn-secondary" @click="$router.push('/quizes')">
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
          <button class="btn-primary" @click="$router.push(`/quizes/${quizId}/play`)">
            Iniciar Quiz
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
                d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useQuizView } from './QuizView'

const { quiz, quizId, getLevelClass, getLevelText, formatDate } = useQuizView()
</script>

<style scoped>
@import '@/styles/views/quizes/quiz-view.css';
</style>

<template>
  <div class="home-container">
    <section class="hero-section">
      <div class="hero-content">
        <h1 class="hero-title">Beready</h1>
        <p class="hero-subtitle">Aprenda inglês de forma inteligente com IA</p>
        <div class="hero-buttons">
          <Button variant="primary" @click="$router.push('/register')">Começar Agora</Button>
          <Button variant="outline" @click="$router.push('/login')">Já tenho conta</Button>
        </div>
      </div>
    </section>

    <section class="features-section">
      <div class="container">
        <h2 class="section-title">Como funciona?</h2>
        <div class="features-grid">
          <div class="feature-card">
            <div class="feature-icon">
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
                  d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                />
              </svg>
            </div>
            <h3>Flashcards Inteligentes</h3>
            <p>Aprenda vocabulário com flashcards adaptativos</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">
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
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
            </div>
            <h3>Quizzes Personalizados</h3>
            <p>Teste seus conhecimentos com quizzes interativos</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">
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
            <h3>Prompts com IA</h3>
            <p>Gere prompts personalizados para praticar conversação</p>
          </div>
        </div>
      </div>
    </section>

    <section class="cta-section">
      <div class="container">
        <h2>Pronto para começar sua jornada?</h2>
        <p>Junte-se a milhares de estudantes que já estão aprendendo com o Beready</p>
        <Button variant="primary" @click="$router.push('/register')">Criar conta gratuita</Button>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { useHome } from './Home'
import Button from '@/shared/components/common/Button.vue'

useHome()
</script>

<style scoped>
@import '@/styles/views/home.css';
</style>

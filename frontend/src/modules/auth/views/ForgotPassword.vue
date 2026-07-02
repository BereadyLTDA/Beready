<template>
  <div class="forgot-password-page">
    <div class="forgot-password-card">
      <div class="forgot-password-header">
        <div class="header-icon">
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
              d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
            />
          </svg>
        </div>
        <h1 class="forgot-password-title">Recuperar Senha</h1>
        <p class="forgot-password-subtitle">Digite seu e-mail para receber o link de recuperação</p>
      </div>

      <form @submit.prevent="handleSubmit">
        <div class="form-group">
          <label class="form-label">E-mail</label>
          <input
            v-model="form.email"
            type="email"
            class="form-input"
            placeholder="seu.email@exemplo.com"
            required
          />
        </div>
        <div class="forgot-password-input-help">
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
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          Enviaremos um link seguro para redefinir sua senha
        </div>
        <button type="submit" class="btn-submit" :disabled="loading">
          {{ loading ? 'Enviando...' : 'Enviar Link de Recuperação' }}
        </button>
      </form>

      <div class="forgot-password-back-link">
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
            d="M10 19l-7-7m0 0l7-7m-7 7h18"
          />
        </svg>
        <router-link to="/login">Voltar para Login</router-link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useForgotPassword } from './useForgotPassword'

const { form, loading, handleSubmit } = useForgotPassword()
</script>

<style scoped>
@import '@/styles/views/users/forgot-password.css';
</style>

<template>
  <div class="login-page">
    <Card class="login-card">
      <template #header>
        <div class="login-header">
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
                d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"
              />
            </svg>
          </div>
          <h1 class="login-title">Entrar</h1>
          <p class="login-subtitle">Faça login para continuar</p>
        </div>
      </template>

      <form @submit.prevent="handleSubmit">
        <Input
          v-model="form.email"
          label="E-mail"
          type="email"
          placeholder="seu.email@exemplo.com"
          required
          :error="errors.email"
        />
        <Input
          v-model="form.password"
          label="Senha"
          type="password"
          placeholder="Sua senha"
          required
          :error="errors.password"
        />
        <div class="login-forgot-link">
          <router-link to="/forgot-password">Esqueceu sua senha?</router-link>
        </div>
        <Button type="submit" :loading="loading" block>Entrar</Button>
      </form>

      <template #footer>
        <div class="login-register-link">
          Não tem uma conta?
          <router-link to="/register">Cadastre-se</router-link>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { useLogin } from './Login'
import Card from '@/shared/components/common/Card.vue'
import Input from '@/shared/components/common/Input.vue'
import Button from '@/shared/components/common/Button.vue'

const { form, errors, loading, handleSubmit } = useLogin()
</script>

<style scoped>
@import '@/styles/views/users/login.css';
</style>

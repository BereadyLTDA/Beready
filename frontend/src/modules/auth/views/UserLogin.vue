<template>
  <AuthCard :title="$t('common.entrar')" :subtitle="$t('login.subtitle')" icon="login">
    <form class="user-login__form flex flex-col gap-4" @submit.prevent="handleSubmit">
      <BaseInput
        v-model="form.email"
        :label="$t('login.email')"
        type="email"
        autocomplete="email"
        :placeholder="$t('register.emailPlaceholder')"
        required
        :error="errors.email"
      />
      <BaseInput
        v-model="form.password"
        :label="$t('login.password')"
        type="password"
        autocomplete="current-password"
        :placeholder="$t('login.passwordPlaceholder')"
        required
        :error="errors.password"
      />
      <div class="user-login__forgot -mt-2 text-end text-sm">
        <router-link to="/forgot-password" class="inline-flex min-h-control items-center">{{ $t('login.forgotPassword') }}</router-link>
      </div>
      <BaseButton type="submit" :loading="loading" block>{{ $t('common.entrar') }}</BaseButton>
    </form>

    <p class="user-login__divider mb-5 mt-6 flex items-center gap-4 text-sm font-medium text-text-subtle before:flex-1 before:border-0 before:border-b before:border-solid before:border-border before:content-empty after:flex-1 after:border-0 after:border-b after:border-solid after:border-border after:content-empty">
      <span>{{ $t('login.or') }}</span>
    </p>

    <div class="user-login__social flex flex-col gap-3">
      <!-- Cores de marca de terceiros nos ícones: exceção aceita pelo design system -->
      <BaseButton variant="secondary" block :disabled="loading" @click="loginWithProvider('google')">
        <img
          src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg"
          width="20"
          height="20"
          alt=""
          class="user-login__social-icon me-2 h-5 w-5 shrink-0 align-middle"
        />
        {{ $t('login.loginWithGoogle') }}
      </BaseButton>

      <BaseButton variant="secondary" block :disabled="loading" @click="loginWithProvider('facebook')">
        <svg viewBox="0 0 24 24" class="user-login__social-icon me-2 h-5 w-5 shrink-0 align-middle" fill="#1877F2" aria-hidden="true">
          <path
            d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"
          />
        </svg>
        {{ $t('login.loginWithFacebook') }}
      </BaseButton>

      <BaseButton variant="secondary" block :disabled="loading" @click="loginWithProvider('linkedin')">
        <svg viewBox="0 0 24 24" class="user-login__social-icon me-2 h-5 w-5 shrink-0 align-middle" fill="#0A66C2" aria-hidden="true">
          <path
            d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"
          />
        </svg>
        {{ $t('login.loginWithLinkedIn') }}
      </BaseButton>
    </div>

    <template #footer>
      {{ $t('login.noAccount') }}
      <router-link to="/register" class="font-semibold">{{ $t('register.title') }}</router-link>
    </template>
  </AuthCard>
</template>

<script setup lang="ts">
import { useLogin } from './Login'
import { BaseButton, BaseInput } from '@/shared/components/ui'
import AuthCard from '../components/AuthCard.vue'

const { form, errors, loading, handleSubmit, loginWithProvider } = useLogin()
</script>

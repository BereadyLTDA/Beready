<template>
  <AuthCard :title="$t('register.title')" :subtitle="$t('register.subtitle')" icon="users" size="lg">
    <!-- Depois do cadastro: a conta só entra depois de confirmar o e-mail -->
    <div v-if="emailEnviado" class="user-register__sent flex flex-col items-center gap-4 text-center" role="status">
      <span class="inline-flex size-16 items-center justify-center rounded-full bg-primary-soft text-icon-xl text-primary-soft-text" aria-hidden="true">
        <BaseIcon name="mail" />
      </span>
      <h2 class="text-xl font-semibold text-text">{{ $t('verificarEmail.confiraTitulo') }}</h2>
      <p class="max-w-measure text-text-muted">{{ $t('verificarEmail.confiraTexto', { email: emailEnviado }) }}</p>
      <div class="flex flex-wrap justify-center gap-3">
        <BaseButton variant="secondary" :loading="reenviando" @click="reenviarLink">{{ $t('verificarEmail.reenviar') }}</BaseButton>
        <BaseButton to="/login">{{ $t('verificarEmail.irParaLogin') }}</BaseButton>
      </div>
    </div>

    <form v-else class="user-register__form flex flex-col gap-6" @submit.prevent="handleSubmit">
      <div class="user-register__grid grid grid-cols-fit-64 gap-6">
        <!-- Seção 1: Informações Pessoais -->
        <fieldset class="user-register__section flex min-w-0 flex-col gap-4 border-0">
          <legend class="user-register__section-title mb-1 inline-flex items-center gap-2 text-base font-semibold text-text">
            <BaseIcon name="user" class="size-5 text-primary" />
            {{ $t('register.personalInfo') }}
          </legend>

          <BaseInput
            v-model="form.nome"
            :label="$t('register.nome')"
            autocomplete="name"
            :placeholder="$t('register.nomePlaceholder')"
            :error="errors.nome"
            required
          />
          <BaseInput
            v-model="form.email"
            type="email"
            :label="$t('login.email')"
            autocomplete="email"
            :placeholder="$t('register.emailPlaceholder')"
            :error="errors.email"
            required
          />
          <BaseInput
            v-model="form.telefone"
            type="tel"
            :label="$t('profile.telefone')"
            autocomplete="tel"
            inputmode="numeric"
            :placeholder="$t('register.telefonePlaceholder')"
            :error="phoneError"
            @input="handlePhoneInput"
            @keydown="handlePhoneKeydown"
          />
        </fieldset>

        <!-- Seção 2: Segurança -->
        <fieldset class="user-register__section flex min-w-0 flex-col gap-4 border-0">
          <legend class="user-register__section-title mb-1 inline-flex items-center gap-2 text-base font-semibold text-text">
            <BaseIcon name="lock-closed" class="size-5 text-primary" />
            {{ $t('register.security') }}
          </legend>

          <div class="user-register__field flex flex-col gap-2">
            <BaseInput
              v-model="form.senha"
              type="password"
              :label="$t('register.senha')"
              autocomplete="new-password"
              :placeholder="$t('register.senhaPlaceholder')"
              :error="errors.senha"
              required
            />
            <PasswordStrength
              v-if="form.senha"
              :level="strengthClass"
              :text="strengthText"
              :width="strengthWidth"
            />
          </div>

          <div class="user-register__field flex flex-col gap-2">
            <BaseInput
              v-model="form.confirmar_senha"
              type="password"
              :label="$t('register.confirmarSenha')"
              autocomplete="new-password"
              :placeholder="$t('register.confirmarSenhaPlaceholder')"
              :error="errors.confirmar_senha"
              required
            />
            <p
              v-if="form.confirmar_senha"
              class="user-register__match inline-flex items-center gap-1 text-xs font-semibold"
              :class="passwordsMatch ? 'user-register__match--ok text-success' : 'user-register__match--error text-danger'"
              aria-live="polite"
            >
              <BaseIcon :name="passwordsMatch ? 'check-circle' : 'x-circle'" />
              <span>{{
                passwordsMatch ? $t('register.passwordsMatch') : $t('register.passwordsDoNotMatch')
              }}</span>
            </p>
          </div>
        </fieldset>

        <!-- Seção 3: Preferências -->
        <fieldset class="user-register__section flex min-w-0 flex-col gap-4 border-0">
          <legend class="user-register__section-title mb-1 inline-flex items-center gap-2 text-base font-semibold text-text">
            <BaseIcon name="book-open" class="size-5 text-primary" />
            {{ $t('register.learningPreferences') }}
          </legend>

          <BaseSelect
            v-model="form.nivel_ingles"
            :label="$t('profile.nivelIngles')"
            :options="nivelOptions"
          />
          <BaseSelect
            v-model="form.idioma_preferido"
            :label="$t('profile.idiomaPreferido')"
            :options="idiomaOptions"
          />
          <BaseTextarea
            v-model="form.objetivos_aprendizado"
            :label="$t('profile.objetivos')"
            :rows="3"
            :placeholder="$t('register.objetivosPlaceholder')"
          />
        </fieldset>
      </div>

      <!-- LGPD: aceite explícito (os links abrem em outra aba para não perder o formulário) -->
      <div class="user-register__aceite flex flex-col gap-1">
        <BaseCheckbox v-model="form.aceite_termos" :aria-invalid="!!errors.aceite_termos" aria-describedby="register-aceite-erro">
          <I18nT keypath="legal.aceite" tag="span" scope="global">
            <template #termos>
              <router-link to="/termos" target="_blank" rel="noopener">{{ $t('legal.termos') }}</router-link>
            </template>
            <template #privacidade>
              <router-link to="/privacidade" target="_blank" rel="noopener">{{ $t('legal.privacidade') }}</router-link>
            </template>
          </I18nT>
        </BaseCheckbox>
        <p v-if="errors.aceite_termos" id="register-aceite-erro" class="text-sm text-danger" role="alert">{{ errors.aceite_termos }}</p>
      </div>

      <div class="user-register__actions flex flex-wrap justify-end gap-3 *:grow *:basis-40 md:*:shrink-0 md:*:grow-0 md:*:basis-auto">
        <BaseButton variant="secondary" to="/login">{{ $t('common.cancelar') }}</BaseButton>
        <BaseButton type="submit" :loading="loading">
          {{ loading ? $t('common.salvando') : $t('register.createAccount') }}
        </BaseButton>
      </div>
    </form>

    <template #footer>
      {{ $t('register.jaTemConta') }}
      <router-link to="/login" class="font-semibold">{{ $t('register.loginLink') }}</router-link>
    </template>
  </AuthCard>
</template>

<script setup lang="ts">
defineOptions({
  name: 'UserRegister',
})

import { I18nT } from 'vue-i18n'
import { BaseButton, BaseCheckbox, BaseIcon, BaseInput, BaseSelect, BaseTextarea } from '@/shared/components/ui'
import AuthCard from '../components/AuthCard.vue'
import PasswordStrength from '../components/PasswordStrength.vue'
import { useRegister } from './Register'

const {
  form,
  errors,
  loading,
  strengthClass,
  strengthText,
  strengthWidth,
  phoneError,
  passwordsMatch,
  nivelOptions,
  idiomaOptions,
  handlePhoneInput,
  handlePhoneKeydown,
  handleSubmit,
  emailEnviado,
  reenviando,
  reenviarLink,
} = useRegister()
</script>

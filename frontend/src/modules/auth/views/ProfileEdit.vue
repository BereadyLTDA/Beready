<template>
  <PageContainer size="md">
    <PageHeader
      :title="`${$t('common.editar')} ${$t('common.perfil')}`"
      :subtitle="$t('profile.editProfileSubtitle')"
      icon="pencil"
      back-to="/profile"
    />

    <form class="edit-profile flex flex-col gap-6" @submit.prevent="handleSubmit">
      <div class="edit-profile__grid grid grid-cols-fit-80 gap-6">
        <BaseCard as="section" :title="$t('profile.personalInfo')">
          <div class="edit-profile__fields flex flex-col gap-4">
            <div class="edit-profile__photo flex flex-wrap items-center gap-4">
              <span class="edit-profile__avatar inline-flex size-18 shrink-0 items-center justify-center overflow-hidden rounded-full border-3 border-solid border-primary-soft bg-primary-soft text-2xl text-primary-soft-text">
                <img
                  v-if="imagePreview || form.foto_perfil"
                  :src="imagePreview || form.foto_perfil"
                  :alt="$t('profile.fotoPerfil')"
                  class="edit-profile__avatar-image h-full w-full object-cover"
                />
                <BaseIcon v-else name="user" />
              </span>
              <BaseField :field-id="photoId" :label="$t('profile.fotoPerfil')" class="edit-profile__photo-field grow basis-48">
                <input
                  :id="photoId"
                  type="file"
                  accept="image/*"
                  class="edit-profile__file min-h-control w-full cursor-pointer rounded-md border border-dashed border-border-strong bg-surface p-2 text-sm text-text-muted focus-visible:focus-ring file:me-3 file:cursor-pointer file:rounded-sm file:border-0 file:bg-primary-soft file:px-3 file:py-2 file:font-semibold file:text-primary-soft-text"
                  @change="handleImageChange"
                />
              </BaseField>
            </div>

            <BaseInput
              v-model="form.nome"
              :label="$t('register.nome')"
              autocomplete="name"
              :placeholder="$t('profile.nomePlaceholder')"
              required
            />
            <BaseInput
              v-model="form.email"
              type="email"
              :label="$t('login.email')"
              autocomplete="email"
              :placeholder="$t('profile.emailPlaceholder')"
              required
            />
            <BaseInput
              v-model="form.telefone"
              type="tel"
              :label="$t('profile.telefone')"
              autocomplete="tel"
              inputmode="numeric"
              :placeholder="$t('profile.telefonePlaceholder')"
              :error="phoneError"
              @input="handlePhoneInput"
              @keydown="handlePhoneKeydown"
            />
          </div>
        </BaseCard>

        <BaseCard as="section" :title="$t('profile.learningPreferences')">
          <div class="edit-profile__fields flex flex-col gap-4">
            <BaseSelect
              v-model="form.nivel_ingles"
              :label="$t('profile.nivelIngles')"
              :placeholder="$t('profile.selecioneNivel')"
              :options="nivelOptions"
            />
            <BaseSelect
              v-model="form.idioma_preferido"
              :label="$t('profile.idiomaPreferido')"
              :placeholder="$t('profile.selecioneIdioma')"
              :options="idiomaOptions"
            />
            <BaseTextarea
              v-model="form.objetivos_aprendizado"
              :label="$t('profile.objetivos')"
              :rows="3"
              :placeholder="$t('profile.objetivosPlaceholder')"
            />
          </div>
        </BaseCard>
      </div>

      <BaseCard as="section" :title="$t('profile.alterarSenha')" :subtitle="$t('profile.senhaHint')">
        <div class="edit-profile__password grid grid-cols-fit-64 items-start gap-x-6 gap-y-4">
          <!-- Aparece ao trocar a senha ou o e-mail: o backend confirma que é o dono da conta -->
          <BaseInput
            v-if="precisaSenhaAtual"
            v-model="form.senha_atual"
            type="password"
            class="edit-profile__current-password col-span-full max-w-md"
            :label="$t('profile.senhaAtual')"
            :hint="$t('profile.senhaAtualHint')"
            autocomplete="current-password"
          />
          <div class="edit-profile__fields flex flex-col gap-4">
            <BaseInput
              v-model="form.nova_senha"
              type="password"
              :label="$t('profile.novaSenha')"
              autocomplete="new-password"
              :placeholder="$t('profile.novaSenhaPlaceholder')"
              @input="handlePasswordInput"
            />
            <PasswordStrength
              v-if="form.nova_senha"
              :level="strengthClass"
              :text="strengthText"
              :width="strengthWidth"
            />
          </div>
          <BaseInput
            v-model="form.confirmar_senha"
            type="password"
            :label="$t('profile.confirmarNovaSenha')"
            autocomplete="new-password"
            :placeholder="$t('profile.confirmarSenhaPlaceholder')"
            :error="!passwordsMatch && form.confirmar_senha ? $t('errors.passwordMatch') : ''"
          />
        </div>
      </BaseCard>

      <div class="edit-profile__actions flex flex-wrap justify-end gap-3 *:grow *:basis-40 md:*:shrink-0 md:*:grow-0 md:*:basis-auto">
        <BaseButton variant="secondary" to="/profile">{{ $t('common.cancelar') }}</BaseButton>
        <BaseButton type="submit" icon="check" :loading="loading">
          {{ loading ? $t('common.carregando') : $t('common.salvar') + ' ' + $t('profile.alteracoes') }}
        </BaseButton>
      </div>
    </form>
  </PageContainer>
</template>

<script setup lang="ts">
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  BaseButton,
  BaseCard,
  BaseField,
  BaseIcon,
  BaseInput,
  BaseSelect,
  BaseTextarea,
  PageContainer,
  PageHeader,
  type SelectOption,
} from '@/shared/components/ui'
import { userLanguageOptions } from '@/locales'
import PasswordStrength from '../components/PasswordStrength.vue'
import { useProfileEdit } from './ProfileEdit'

const { t } = useI18n()
const photoId = `profile-photo-${useId()}`

const nivelOptions = computed<SelectOption[]>(() => [
  { value: 'iniciante', label: t('profile.nivelIniciante') },
  { value: 'intermediario', label: t('profile.nivelIntermediario') },
  { value: 'avancado', label: t('profile.nivelAvancado') },
])

const idiomaOptions = computed<SelectOption[]>(() => userLanguageOptions(t))

const {
  form,
  loading,
  strengthClass,
  strengthText,
  strengthWidth,
  phoneError,
  passwordsMatch,
  imagePreview,
  handlePhoneInput,
  handlePhoneKeydown,
  handlePasswordInput,
  handleImageChange,
  handleSubmit,
  precisaSenhaAtual,
} = useProfileEdit()
</script>

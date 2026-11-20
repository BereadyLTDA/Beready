<template>
  <PageContainer>
    <PageHeader
      :title="user?.nome || $t('common.carregando')"
      :subtitle="user?.email || ''"
      icon="user"
      back-to="/dashboard"
    >
      <template #actions>
        <BaseButton variant="secondary" icon="pencil" to="/profile/edit">
          {{ $t('common.editar') }} {{ $t('common.perfil') }}
        </BaseButton>
        <BaseButton variant="secondary" icon="download" :loading="exportando" @click="exportarDados">
          {{ $t('profile.exportar') }}
        </BaseButton>
        <BaseButton variant="danger" icon="trash" @click="showDeleteModal = true">
          {{ $t('profile.deleteAccount') }}
        </BaseButton>
      </template>
    </PageHeader>

    <div class="user-profile__grid grid grid-cols-fit-88 gap-6">
      <BaseCard as="section" :title="$t('profile.personalInfo')">
        <div class="user-profile__identity flex flex-wrap items-start gap-5">
          <span class="user-profile__avatar inline-flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-full border-3 border-solid border-primary-soft bg-primary-soft text-3xl text-primary-soft-text">
            <img
              v-if="user?.foto_perfil"
              :src="user.foto_perfil"
              :alt="$t('profile.fotoPerfil')"
              class="user-profile__avatar-image h-full w-full object-cover"
            />
            <BaseIcon v-else name="user" />
          </span>
          <dl class="user-profile__list flex min-w-0 grow basis-56 flex-col gap-5">
            <div class="user-profile__item">
              <dt class="user-profile__label mb-1 text-xs font-semibold uppercase tracking-label text-text-muted">{{ $t('register.nome') }}</dt>
              <dd class="user-profile__value wrap-anywhere text-base text-text">{{ user?.nome || '-' }}</dd>
            </div>
            <div class="user-profile__item">
              <dt class="user-profile__label mb-1 text-xs font-semibold uppercase tracking-label text-text-muted">{{ $t('login.email') }}</dt>
              <dd class="user-profile__value wrap-anywhere text-base text-text">{{ user?.email || '-' }}</dd>
            </div>
            <div class="user-profile__item">
              <dt class="user-profile__label mb-1 text-xs font-semibold uppercase tracking-label text-text-muted">{{ $t('profile.telefone') }}</dt>
              <dd class="user-profile__value wrap-anywhere text-base text-text">{{ formattedPhone || $t('profile.naoInformado') }}</dd>
            </div>
          </dl>
        </div>
      </BaseCard>

      <BaseCard as="section" :title="$t('profile.learningPreferences')">
        <dl class="user-profile__list flex min-w-0 grow basis-56 flex-col gap-5">
          <div class="user-profile__item">
            <dt class="user-profile__label mb-1 text-xs font-semibold uppercase tracking-label text-text-muted">{{ $t('profile.nivelIngles') }}</dt>
            <dd class="user-profile__value wrap-anywhere text-base text-text">{{ getNivelIngles(user?.nivel_ingles) }}</dd>
          </div>
          <div class="user-profile__item">
            <dt class="user-profile__label mb-1 text-xs font-semibold uppercase tracking-label text-text-muted">{{ $t('profile.idiomaPreferido') }}</dt>
            <dd class="user-profile__value wrap-anywhere text-base text-text">{{ getIdiomaPreferido(user?.idioma_preferido) }}</dd>
          </div>
          <div class="user-profile__item">
            <dt class="user-profile__label mb-1 text-xs font-semibold uppercase tracking-label text-text-muted">{{ $t('profile.status') }}</dt>
            <dd class="user-profile__value wrap-anywhere text-base text-text">
              <BaseBadge :variant="user?.status === 'ativo' ? 'success' : 'danger'" icon>
                {{ user?.status === 'ativo' ? $t('profile.ativo') : $t('profile.inativo') }}
              </BaseBadge>
            </dd>
          </div>
        </dl>
      </BaseCard>
    </div>

    <BaseCard as="section" :title="$t('profile.learningGoals')">
      <p class="user-profile__goals wrap-anywhere whitespace-pre-line leading-relaxed text-text">
        {{ user?.objetivos_aprendizado || $t('profile.noGoals') }}
      </p>
    </BaseCard>

    <BaseModal v-model="showDeleteModal" :title="$t('profile.deleteAccount')" size="sm">
      <div class="user-profile__delete flex flex-col gap-4">
        <p class="user-profile__delete-text text-text">{{ $t('profile.deleteConfirmMessage') }}</p>
        <BaseAlert variant="danger" :message="$t('profile.deleteWarning')" />
        <p class="user-profile__delete-apaga text-sm text-text-muted">{{ $t('profile.deleteApaga') }}</p>
        <BaseInput
          v-model="confirmEmail"
          type="email"
          :label="`${$t('profile.deleteConfirmLabel')} ${user?.email || ''}:`"
          :placeholder="user?.email || ''"
          autocomplete="off"
        />
        <BaseInput
          v-model="senhaExclusao"
          type="password"
          :label="$t('profile.deleteSenha')"
          :hint="$t('profile.deleteSenhaHint')"
          autocomplete="current-password"
        />
      </div>
      <template #footer="{ close }">
        <BaseButton variant="secondary" @click="close">{{ $t('common.cancelar') }}</BaseButton>
        <BaseButton
          variant="danger"
          icon="trash"
          :loading="deleteLoading"
          :disabled="confirmEmail !== user?.email"
          @click="handleDeleteAccount"
        >
          {{ deleteLoading ? $t('common.carregando') : $t('profile.confirmDelete') }}
        </BaseButton>
      </template>
    </BaseModal>
  </PageContainer>
</template>

<script setup lang="ts">
import {
  BaseAlert,
  BaseBadge,
  BaseButton,
  BaseCard,
  BaseIcon,
  BaseInput,
  BaseModal,
  PageContainer,
  PageHeader,
} from '@/shared/components/ui'
import { useProfile } from './useProfile'

const {
  user,
  formattedPhone,
  showDeleteModal,
  confirmEmail,
  deleteLoading,
  getNivelIngles,
  getIdiomaPreferido,
  handleDeleteAccount,
  senhaExclusao,
  exportando,
  exportarDados,
} = useProfile()
</script>

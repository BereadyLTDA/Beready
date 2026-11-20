<template>
  <main class="auth-card flex min-h-viewport flex-col items-center justify-center bg-bg px-gutter py-6">
    <BaseCard class="auth-card__card w-full" :class="sizeClasses[size]" padding="lg">
      <template #header>
        <div class="auth-card__header flex flex-col items-center gap-2 text-center">
          <span class="auth-card__icon mb-2 inline-flex size-18 items-center justify-center rounded-full bg-primary-soft text-3xl text-primary-soft-text" aria-hidden="true">
            <BaseIcon :name="icon" />
          </span>
          <h1 class="auth-card__title text-fluid-2xl-3xl font-bold leading-tight text-text">{{ title }}</h1>
          <p v-if="subtitle" class="auth-card__subtitle text-text-muted">{{ subtitle }}</p>
        </div>
      </template>

      <slot />

      <template v-if="$slots.footer" #footer>
        <div class="auth-card__footer w-full text-center text-sm text-text-muted">
          <slot name="footer" />
        </div>
      </template>
    </BaseCard>
    <LinksLegais class="mt-4" />
  </main>
</template>

<script setup lang="ts">
// Moldura comum das telas públicas de autenticação (login, cadastro,
// esqueci/redefinir senha, retorno OAuth): página centralizada + card com
// ícone, título (<h1>) e subtítulo. Só apresentação.
import { BaseCard, BaseIcon, type IconName } from '@/shared/components/ui'
import LinksLegais from '@/components/layout/LinksLegais.vue'

type Size = 'sm' | 'lg'

withDefaults(
  defineProps<{
    title: string
    subtitle?: string
    icon?: IconName
    /** sm ≈ 28rem (formulários curtos) · lg ≈ 60rem (cadastro em colunas) */
    size?: Size
  }>(),
  { subtitle: '', icon: 'user', size: 'sm' },
)

defineSlots<{ default?: () => unknown; footer?: () => unknown }>()

const sizeClasses: Record<Size, string> = {
  sm: 'max-w-112',
  lg: 'max-w-container-md',
}
</script>

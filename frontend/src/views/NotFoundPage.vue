<template>
  <!-- Endereço inexistente: antes caía numa tela em branco -->
  <PageContainer as="main" class="not-found flex min-h-viewport items-center justify-center">
    <h1 class="sr-only">{{ $t('errors.notFound') }}</h1>
    <EmptyState icon="exclamation-circle" title-tag="h2" :title="$t('errors.notFound')">
      <BaseButton :to="authStore.isAuthenticated ? '/dashboard' : '/login'" icon="arrow-left">
        {{ authStore.isAuthenticated ? $t('common.voltarDashboard') : $t('common.voltar') }}
      </BaseButton>
    </EmptyState>
  </PageContainer>
</template>

<script setup lang="ts">
import { BaseButton, EmptyState, PageContainer } from '@/shared/components/ui'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
</script>

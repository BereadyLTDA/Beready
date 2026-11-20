<template>
  <!-- Página pública: termos de uso ou política de privacidade -->
  <AppNavbar v-if="authStore.isAuthenticated" />
  <PageContainer as="main" size="md" class="legal">
    <PageHeader
      :title="tipo === 'termos' ? $t('legal.termos') : $t('legal.privacidade')"
      :subtitle="$t('legal.atualizadoEm', { data: formatDate(`${VERSAO}T12:00:00`) })"
      :icon="tipo === 'termos' ? 'document' : 'shield-check'"
      :back-to="authStore.isAuthenticated ? '/dashboard' : '/login'"
    />

    <BaseAlert v-if="idiomaTexto === 'en' && !locale.startsWith('en')" variant="info" class="mb-4" :message="$t('legal.soPtEn')" />

    <BaseCard as="article" :lang="idiomaTexto === 'pt' ? 'pt-BR' : 'en'">
      <section v-for="secao in secoes" :key="secao.titulo" class="legal__secao mb-6 last:mb-0">
        <h2 class="mb-2 text-lg font-semibold text-text">{{ secao.titulo }}</h2>
        <p v-for="(p, i) in secao.paragrafos" :key="i" class="mb-2 leading-relaxed text-text">
          <template v-for="(parte, j) in comContato(p)" :key="j">
            <a v-if="parte.link" :href="parte.link" class="font-semibold">{{ parte.texto }}</a>
            <template v-else>{{ parte.texto }}</template>
          </template>
        </p>
      </section>
    </BaseCard>

    <p class="mt-4 text-center text-sm">
      <router-link :to="tipo === 'termos' ? '/privacidade' : '/termos'">
        {{ tipo === 'termos' ? $t('legal.privacidade') : $t('legal.termos') }}
      </router-link>
    </p>
  </PageContainer>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppNavbar from '@/components/layout/AppNavbar.vue'
import { BaseAlert, BaseCard, PageContainer, PageHeader } from '@/shared/components/ui'
import { CONTATO_EMAIL } from '@/shared/config/env'
import { formatDate } from '@/shared/utils/intl'
import { useAuthStore } from '@/stores/auth'
import { CONTEUDO, VERSAO } from '../conteudo'

const props = defineProps<{ tipo: 'termos' | 'privacidade' }>()

const { locale } = useI18n()
const authStore = useAuthStore()

// Texto completo só em português e inglês; os demais idiomas leem a versão em inglês
const idiomaTexto = computed<'pt' | 'en'>(() => (locale.value.startsWith('pt') ? 'pt' : 'en'))
const secoes = computed(() => CONTEUDO[idiomaTexto.value][props.tipo])

const REPOSITORIO = 'https://github.com/BereadyLTDA/Beready'

// Troca {contato} pelo e-mail (mailto) ou, sem ele, pelo repositório do projeto
const comContato = (texto: string): { texto: string; link?: string }[] => {
  const partes = texto.split('{contato}')
  if (partes.length === 1) return [{ texto }]
  const contato = CONTATO_EMAIL
    ? { texto: CONTATO_EMAIL, link: `mailto:${CONTATO_EMAIL}` }
    : { texto: REPOSITORIO.replace('https://', ''), link: REPOSITORIO }
  return partes.flatMap((parte, i) => (i === 0 ? [{ texto: parte }] : [contato, { texto: parte }]))
}
</script>

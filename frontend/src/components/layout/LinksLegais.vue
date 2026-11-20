<template>
  <nav class="links-legais flex flex-wrap justify-center gap-x-4 gap-y-1 text-xs text-text-muted" :aria-label="$t('legal.rodape')">
    <router-link to="/termos" class="text-text-muted">{{ $t('legal.termos') }}</router-link>
    <router-link to="/privacidade" class="text-text-muted">{{ $t('legal.privacidade') }}</router-link>
  </nav>
</template>

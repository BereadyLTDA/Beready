<template>
  <!-- Some quando o navegador não tem síntese de voz -->
  <span v-if="suportado" class="ui-ouvir inline-flex items-center gap-1">
    <BaseButton
      variant="ghost"
      size="sm"
      icon="speaker"
      :aria-label="$t('pronuncia.ouvir')"
      :title="$t('pronuncia.ouvir')"
      :aria-pressed="falando && !lentoAtivo"
      @click="tocar(false)"
    />
    <BaseButton
      v-if="comLento"
      variant="ghost"
      size="sm"
      :aria-label="$t('pronuncia.ouvirDevagar')"
      :title="$t('pronuncia.ouvirDevagar')"
      :aria-pressed="falando && lentoAtivo"
      @click="tocar(true)"
    >
      <span aria-hidden="true" class="text-xs font-semibold">0.7×</span>
    </BaseButton>
  </span>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import BaseButton from './BaseButton.vue'
import { useFala } from '@/shared/composables/useFala'

const props = withDefaults(defineProps<{ texto: string; lang?: string; comLento?: boolean }>(), {
  lang: 'en-US',
  comLento: true,
})

const { suportado, falando, falar } = useFala()
const lentoAtivo = ref(false)

const tocar = (lento: boolean) => {
  lentoAtivo.value = lento
  falar(props.texto, { lang: props.lang, lento })
}
</script>

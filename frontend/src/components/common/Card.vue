<template>
  <div class="card" :class="getVariantClass(variant)">
    <div v-if="$slots.header" class="card-header">
      <slot name="header" />
    </div>
    <div class="card-body">
      <slot />
    </div>
    <div v-if="$slots.footer" class="card-footer">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { useCard } from './Card'

defineProps({
  variant: {
    type: String,
    default: 'default',
    validator: (value: string) =>
      ['default', 'primary', 'success', 'danger', 'warning'].includes(value),
  },
})

const { getVariantClass } = useCard()
</script>

<style scoped>
@import '@/styles/components/card.css';
</style>

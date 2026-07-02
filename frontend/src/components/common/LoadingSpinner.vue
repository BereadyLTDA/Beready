<template>
  <div class="spinner-container" :class="{ 'spinner-fullscreen': fullscreen }">
    <div class="spinner" :class="`spinner-${size}`">
      <div class="spinner-circle"></div>
    </div>
    <p v-if="text" class="spinner-text">{{ text }}</p>
  </div>
</template>

<script setup lang="ts">
defineProps({
  size: {
    type: String,
    default: 'medium',
    validator: (value: string) => ['small', 'medium', 'large'].includes(value),
  },
  text: {
    type: String,
    default: '',
  },
  fullscreen: {
    type: Boolean,
    default: false,
  },
})
</script>

<style scoped>
@import '@/styles/components/spinner.css';
</style>

<template>
  <div class="alert-container">
    <Alert
      v-for="alert in alerts"
      :key="alert.id"
      :message="alert.message"
      :type="alert.type"
      :duration="alert.duration"
      @close="removeAlert(alert.id)"
    />
  </div>
</template>

<script setup lang="ts">
import { useAlert } from '@/shared/composables/useAlert'
import Alert from './Alert.vue'

const { alerts, removeAlert } = useAlert()
</script>

<style scoped>
.alert-container {
  position: fixed;
  top: 1rem;
  right: 1rem;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-width: 90vw;
}

@media (max-width: 640px) {
  .alert-container {
    left: 1rem;
    right: 1rem;
  }
  
  .alert-container .alert {
    max-width: none;
    width: 100%;
  }
}
</style>

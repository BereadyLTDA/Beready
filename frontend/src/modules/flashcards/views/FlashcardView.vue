<template>
  <div class="flashcard-view-page">
    <div class="flashcard-view-hero">
      <button class="hero-back-btn" @click="$router.push('/flashcards')">← Voltar</button>
      <h1>Detalhes do Flashcard</h1>
    </div>
    <div v-if="flashcard" class="flashcard-detail">
      <h2>{{ flashcard.frente }}</h2>
      <p>{{ flashcard.verso }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/core/services/api'

const route = useRoute()
const flashcard = ref<any>(null)

onMounted(async () => {
  const id = route.params.id
  if (id) {
    const response = await api.get(`/flashcards/view/${id}`)
    flashcard.value = response.data.data
  }
})
</script>

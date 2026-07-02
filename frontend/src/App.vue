<template>
  <div id="app">
    <router-view />
    <AlertContainer />
  </div>
</template>

<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import AlertContainer from '@/shared/components/common/AlertContainer.vue'
import { useAuth } from '@/shared/composables/useAuth'
import api from '@/core/services/api'

// Páginas públicas que NÃO devem ter tema escuro/daltônico
const publicRoutes = ['/', '/login', '/register', '/forgot-password', '/reset-password']

const { isAuthenticated, user } = useAuth()

const loadUserPreferences = async () => {
  const currentRoute = window.location.pathname

  const isPublicRoute = publicRoutes.some(
    (route) => currentRoute === route || currentRoute.startsWith('/reset-password'),
  )

  if (isPublicRoute) {
    document.documentElement.classList.remove('dark-mode')
    document.body.classList.remove('dark-mode')
    document.documentElement.classList.remove('daltonico-mode')
    document.body.classList.remove('daltonico-mode')
    return
  }

  if (!isAuthenticated.value || !user.value?.id) {
    return
  }

  try {
    const response = await api.get(`/preferencias/usuario/${user.value.id}`)
    if (response.data.success && response.data.data) {
      if (response.data.data.tema === 'escuro') {
        document.documentElement.classList.add('dark-mode')
        document.body.classList.add('dark-mode')
      } else {
        document.documentElement.classList.remove('dark-mode')
        document.body.classList.remove('dark-mode')
      }

      if (response.data.data.modo_daltonico) {
        document.documentElement.classList.add('daltonico-mode')
        document.body.classList.add('daltonico-mode')
      } else {
        document.documentElement.classList.remove('daltonico-mode')
        document.body.classList.remove('daltonico-mode')
      }
    }
  } catch (err) {
    console.error('Erro ao carregar preferências:', err)
  }
}

// Monitorar mudanças de rota
const route = useRoute()
watch(
  () => route.path,
  () => {
    loadUserPreferences()
  },
  { immediate: true },
)

// Monitorar mudanças no usuário (login/logout)
watch(
  () => user.value,
  () => {
    loadUserPreferences()
  },
  { immediate: true },
)

onMounted(() => {
  loadUserPreferences()
})
</script>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html,
body,
#app {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

body {
  font-family:
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    Roboto,
    'Helvetica Neue',
    Arial,
    sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

svg {
  width: 1em;
  height: 1em;
  flex-shrink: 0;
}

button svg {
  pointer-events: none;
}
</style>

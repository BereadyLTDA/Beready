<template>
  <div class="dashboard-container">
    <Navbar :user="user" :loading="loading" @logout="handleLogout" />

    <main class="dashboard-main">
      <div class="welcome-section">
        <h1 class="welcome-title">Bem-vindo de volta, {{ userName }}!</h1>
        <p class="welcome-subtitle">{{ motivationalMessage }}</p>
      </div>

      <!-- PAINEL ADMIN -->
      <div v-if="isAdmin" class="admin-banner">
        <div class="admin-banner-icon">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
            />
          </svg>
        </div>
        <div class="admin-banner-content">
          <span class="admin-badge">Administrador</span>
          <div class="admin-links">
            <button @click="$router.push('/admin')" class="admin-link-btn">
              Gerenciar Usuários
            </button>
            <button @click="$router.push('/admin?tab=stats')" class="admin-link-btn">
              Estatísticas
            </button>
          </div>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon bg-blue-100">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
              />
            </svg>
          </div>
          <div class="stat-info">
            <h3 class="stat-value">{{ stats.flashcardsCount || 0 }}</h3>
            <p class="stat-label">Flashcards Estudados</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon bg-green-100">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div class="stat-info">
            <h3 class="stat-value">{{ stats.acertoRate || 0 }}%</h3>
            <p class="stat-label">Taxa de Acerto</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon bg-purple-100">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div class="stat-info">
            <h3 class="stat-value">{{ stats.sequenciaAtual || 0 }}</h3>
            <p class="stat-label">Dias de Sequência</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon bg-yellow-100">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div class="stat-info">
            <h3 class="stat-value">{{ stats.tempoEstudo || '0h' }}</h3>
            <p class="stat-label">Tempo de Estudo</p>
          </div>
        </div>
      </div>

      <!-- Progress Section -->
      <div class="progress-section">
        <div class="progress-card">
          <h3 class="progress-title">Progresso Geral</h3>
          <div class="progress-bar-container">
            <div class="progress-bar" :style="{ width: (stats.progressoGeral || 0) + '%' }"></div>
          </div>
          <p class="progress-text">{{ stats.progressoGeral || 0 }}% completo - Continue assim!</p>
        </div>
      </div>

      <!-- Features Grid -->
      <div class="dashboard-features-grid">
        <div class="dashboard-feature-card" @click="$router.push('/flashcards')">
          <div class="feature-icon-large bg-blue-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="white">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
              />
            </svg>
          </div>
          <div class="feature-content">
            <h3 class="feature-title">Flashcards</h3>
            <p class="feature-description">
              Estude com flashcards interativos e acelere seu aprendizado
            </p>
            <span class="feature-link">Começar agora →</span>
          </div>
        </div>

        <div class="dashboard-feature-card" @click="$router.push('/quizes')">
          <div class="feature-icon-large bg-green-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="white">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
              />
            </svg>
          </div>
          <div class="feature-content">
            <h3 class="feature-title">Quizes</h3>
            <p class="feature-description">Teste seus conhecimentos com quizzes personalizados</p>
            <span class="feature-link">Testar conhecimento →</span>
          </div>
        </div>

        <div class="dashboard-feature-card" @click="$router.push('/prompts')">
          <div class="feature-icon-large bg-purple-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="white">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
              />
            </svg>
          </div>
          <div class="feature-content">
            <h3 class="feature-title">Prompts com IA</h3>
            <p class="feature-description">Gere prompts personalizados para praticar conversação</p>
            <span class="feature-link">Gerar prompts →</span>
          </div>
        </div>

        <div class="dashboard-feature-card" @click="$router.push('/tags')">
          <div class="feature-icon-large bg-orange-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="white">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-5-5A1.994 1.994 0 013 12V7a4 4 0 014-4z"
              />
            </svg>
          </div>
          <div class="feature-content">
            <h3 class="feature-title">Tags</h3>
            <p class="feature-description">Organize seus flashcards com tags personalizadas</p>
            <span class="feature-link">Gerenciar tags →</span>
          </div>
        </div>

        <div class="dashboard-feature-card" @click="$router.push('/progresso')">
          <div class="feature-icon-large bg-indigo-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="white">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
              />
            </svg>
          </div>
          <div class="feature-content">
            <h3 class="feature-title">Meu Progresso</h3>
            <p class="feature-description">Acompanhe suas estatísticas e evolução</p>
            <span class="feature-link">Ver estatísticas →</span>
          </div>
        </div>

        <div class="dashboard-feature-card" @click="$router.push('/preferencias')">
          <div class="feature-icon-large bg-gray-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="white">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
              />
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
              />
            </svg>
          </div>
          <div class="feature-content">
            <h3 class="feature-title">Preferências</h3>
            <p class="feature-description">Personalize sua experiência no aplicativo</p>
            <span class="feature-link">Configurar →</span>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { useDashboard } from './Dashboard'
import Navbar from '@/components/layout/Navbar.vue'
import { useAuth } from '@/shared/composables/useAuth'

const { user, loading, userName, stats, motivationalMessage, handleLogout } = useDashboard()
const { isAdmin } = useAuth()
</script>

<style scoped>
@import '@/styles/views/dashboard.css';

/* PAINEL ADMIN */

.admin-banner {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 16px;
  padding: 1rem 1.5rem;
  margin-bottom: 2rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25);
}

.admin-banner-icon {
  width: 48px;
  height: 48px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.admin-banner-icon svg {
  width: 24px;
  height: 24px;
  color: white;
}

.admin-banner-content {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
}

.admin-badge {
  background: rgba(255, 255, 255, 0.2);
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  color: white;
}

.admin-links {
  display: flex;
  gap: 1rem;
}

.admin-link-btn {
  background: rgba(255, 255, 255, 0.15);
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  color: white;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.admin-link-btn:hover {
  background: rgba(255, 255, 255, 0.25);
  transform: translateY(-1px);
}

/* Responsividade do banner admin */
@media (max-width: 640px) {
  .admin-banner {
    flex-direction: column;
    text-align: center;
    padding: 1rem;
  }

  .admin-banner-content {
    flex-direction: column;
    width: 100%;
  }

  .admin-links {
    width: 100%;
    justify-content: center;
  }
}

/* ============================================
   DARK MODE - ADMIN BANNER
   ============================================ */
.dark-mode .admin-banner {
  background: linear-gradient(135deg, #4c51bf 0%, #5b3a7a 100%);
}
</style>

<template>
  <div class="admin-panel-page">
    <!-- Header com gradiente -->
    <div class="admin-header">
      <button class="back-btn" @click="$router.push('/dashboard')">
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
            d="M10 19l-7-7m0 0l7-7m-7 7h18"
          />
        </svg>
        Voltar
      </button>
      <div class="header-content">
        <div class="header-icon">
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
        <div class="header-text">
          <h1>Painel Administrativo</h1>
          <p>Bem-vindo, {{ user?.nome }} <span class="admin-chip">Administrador</span></p>
        </div>
      </div>
    </div>

    <!-- Tabs estilizadas -->
    <div class="admin-tabs">
      <button :class="['tab-btn', { active: activeTab === 'users' }]" @click="activeTab = 'users'">
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
        Usuários
      </button>
      <button :class="['tab-btn', { active: activeTab === 'stats' }]" @click="activeTab = 'stats'">
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
            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
          />
        </svg>
        Estatísticas
      </button>
    </div>

    <!-- Tab Usuários -->
    <div v-if="activeTab === 'users'" class="admin-users">
      <div class="users-header">
        <h2>Gerenciar Usuários</h2>
        <div class="search-box">
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
              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
            />
          </svg>
          <input type="text" v-model="searchQuery" placeholder="Buscar por nome ou e-mail..." />
        </div>
      </div>

      <div v-if="loadingUsers" class="loading-state">
        <div class="spinner"></div>
        <p>Carregando usuários...</p>
      </div>

      <div v-else class="users-table-wrapper">
        <table class="users-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Usuário</th>
              <th>E-mail</th>
              <th>Nível</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="userItem in filteredUsers" :key="userItem.id">
              <td>
                <span class="user-id">#{{ userItem.id }}</span>
              </td>
              <td>
                <div class="user-name-cell">
                  <div class="user-avatar">{{ userItem.nome?.charAt(0) || 'U' }}</div>
                  <span class="user-name">{{ userItem.nome }}</span>
                </div>
              </td>
              <td>{{ userItem.email }}</td>
              <td>
                <span :class="['role-badge', userItem.role]">
                  {{ userItem.role === 'admin' ? '👑 Admin' : '👤 Usuário' }}
                </span>
              </td>
              <td>
                <span :class="['status-badge', userItem.status]">
                  {{ userItem.status === 'ativo' ? '🟢 Ativo' : '🔴 Inativo' }}
                </span>
              </td>
              <td>
                <button
                  v-if="userItem.id !== currentUserId"
                  @click="toggleRole(userItem)"
                  class="action-btn"
                  :class="userItem.role === 'admin' ? 'rebaixar' : 'promover'"
                  :disabled="updatingRole === userItem.id"
                >
                  {{
                    updatingRole === userItem.id
                      ? '...'
                      : userItem.role === 'admin'
                        ? 'Rebaixar'
                        : 'Promover'
                  }}
                </button>
                <span v-else class="current-user-badge">Você</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tab Estatísticas -->
    <div v-if="activeTab === 'stats'" class="admin-stats">
      <div class="stats-cards">
        <div class="stat-card">
          <div class="stat-icon purple">
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
          <div class="stat-value">{{ stats.total_users || 0 }}</div>
          <div class="stat-label">Total de Usuários</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue">
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
          <div class="stat-value">{{ stats.total_flashcards || 0 }}</div>
          <div class="stat-label">Flashcards</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">
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
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
              />
            </svg>
          </div>
          <div class="stat-value">{{ stats.total_quizes || 0 }}</div>
          <div class="stat-label">Quizes</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">
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
                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
              />
            </svg>
          </div>
          <div class="stat-value">{{ stats.total_prompts || 0 }}</div>
          <div class="stat-label">Prompts</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useAuth } from '@/shared/composables/useAuth'
import { useAlert } from '@/shared/composables/useAlert'
import api from '@/core/services/api'

const { user } = useAuth()
const activeTab = ref('users')
const users = ref<any[]>([])
const loadingUsers = ref(false)
const updatingRole = ref<number | null>(null)
const searchQuery = ref('')
const stats = ref({
  total_users: 0,
  total_flashcards: 0,
  total_quizes: 0,
  total_prompts: 0,
})

const currentUserId = computed(() => user.value?.id)

const filteredUsers = computed(() => {
  if (!searchQuery.value) return users.value
  const query = searchQuery.value.toLowerCase()
  return users.value.filter(
    (u) => u.nome?.toLowerCase().includes(query) || u.email?.toLowerCase().includes(query),
  )
})

const loadUsers = async () => {
  loadingUsers.value = true
  try {
    const response = await api.get('/admin/users')

    // Verifica se response.data é string (caso o backend ainda retorne string)
    let data = response.data
    if (typeof data === 'string') {
      try {
        data = JSON.parse(data)
      } catch (e) {
        console.error('Erro ao fazer parse da resposta:', e)
      }
    }

    if (data && data.success) {
      users.value = data.data || []
    } else {
      console.error('Erro na resposta:', data?.message)
      users.value = []
    }
  } catch (err) {
    console.error('Erro ao carregar usuários:', err)
    users.value = []
  } finally {
    loadingUsers.value = false
  }
}

const loadStats = async () => {
  try {
    const response = await api.get('/admin/stats')
    if (response.data.success) {
      stats.value = response.data.data
    }
  } catch (err) {
    console.error('Erro ao carregar estatísticas:', err)
  }
}

const { success, error, warning, info } = useAlert()

const toggleRole = async (targetUser: any) => {
  const newRole = targetUser.role === 'admin' ? 'user' : 'admin'
  const newRoleText = newRole === 'admin' ? 'Administrador' : 'Usuário'
  const oldRoleText = targetUser.role === 'admin' ? 'Administrador' : 'Usuário'

  updatingRole.value = targetUser.id

  try {
    const response = await api.post('/admin/users/role', {
      user_id: targetUser.id,
      role: newRole,
    })

    if (response.data.success) {
      await loadUsers()

      // ✅ Mensagem padronizada com sucesso
      success(`${targetUser.nome} agora é ${newRoleText}`)
    } else {
      // ✅ Mensagem de erro padronizada
      error(response.data.message || 'Erro ao alterar permissão')
    }
  } catch (err: any) {
    console.error('Erro:', err)
    // ✅ Mensagem de erro padronizada
    error(err.response?.data?.message || 'Erro ao alterar permissão')
  } finally {
    updatingRole.value = null
  }
}

onMounted(() => {
  loadUsers()
  loadStats()
})
</script>

<style scoped>
/* ============================================
   ADMIN PANEL - ESTILO MODERNO
   ============================================ */

.admin-panel-page {
  min-height: 100vh;
  background: #f7fafc;
}

/* Header com gradiente */
.admin-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 2rem;
  color: white;
  margin-bottom: 2rem;
}

.back-btn {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  cursor: pointer;
  font-size: 0.875rem;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  transition: all 0.2s ease;
}

.back-btn svg {
  width: 16px;
  height: 16px;
}

.back-btn:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: translateX(-2px);
}

.header-content {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.header-icon {
  width: 60px;
  height: 60px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.header-icon svg {
  width: 32px;
  height: 32px;
}

.header-text h1 {
  font-size: 1.75rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.header-text p {
  font-size: 0.875rem;
  opacity: 0.9;
}

.admin-chip {
  background: rgba(255, 255, 255, 0.2);
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  margin-left: 0.5rem;
}

/* Tabs */
.admin-tabs {
  display: flex;
  gap: 0.5rem;
  padding: 0 2rem;
  margin-bottom: 2rem;
}

.tab-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  background: white;
  border: none;
  border-radius: 12px;
  font-weight: 500;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.tab-btn svg {
  width: 18px;
  height: 18px;
}

.tab-btn.active {
  background: #667eea;
  color: white;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.tab-btn:hover:not(.active) {
  background: #edf2f7;
  transform: translateY(-1px);
}

/* Conteúdo principal */
.admin-users,
.admin-stats {
  padding: 0 2rem 2rem;
}

/* Cabeçalho da tabela */
.users-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.users-header h2 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #2d3748;
}

/* Busca */
.search-box {
  position: relative;
}

.search-box svg {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  width: 18px;
  height: 18px;
  color: #a0aec0;
}

.search-box input {
  padding: 0.625rem 1rem 0.625rem 2.5rem;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  width: 280px;
  font-size: 0.875rem;
  transition: all 0.2s ease;
}

.search-box input:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Tabela */
.users-table-wrapper {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.users-table {
  width: 100%;
  border-collapse: collapse;
}

.users-table th {
  padding: 1rem;
  text-align: left;
  background: #f8fafc;
  font-weight: 600;
  color: #4a5568;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1px solid #e2e8f0;
}

.users-table td {
  padding: 1rem;
  border-bottom: 1px solid #edf2f7;
  color: #2d3748;
  font-size: 0.875rem;
}

/* Célula do usuário */
.user-name-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-avatar {
  width: 32px;
  height: 32px;
  background: linear-gradient(135deg, #667eea, #764ba2);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 0.875rem;
  text-transform: uppercase;
}

.user-id {
  color: #a0aec0;
  font-size: 0.75rem;
}

/* Badges */
.role-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 500;
}

.role-badge.admin {
  background: #fed7d7;
  color: #9b2c2c;
}

.role-badge.user {
  background: #bee3f8;
  color: #2b6cb0;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 500;
}

.status-badge.ativo {
  background: #c6f6d5;
  color: #276749;
}

/* Botões de ação */
.action-btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  font-size: 0.75rem;
  transition: all 0.2s ease;
}

.action-btn.promover {
  background: #c6f6d5;
  color: #276749;
}

.action-btn.promover:hover:not(:disabled) {
  background: #9ae6b4;
  transform: scale(1.02);
}

.action-btn.rebaixar {
  background: #fed7d7;
  color: #9b2c2c;
}

.action-btn.rebaixar:hover:not(:disabled) {
  background: #feb2b2;
  transform: scale(1.02);
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.current-user-badge {
  font-size: 0.75rem;
  color: #a0aec0;
  background: #edf2f7;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
}

/* Estatísticas */
.stats-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.5rem;
}

.stat-card {
  background: white;
  padding: 1.5rem;
  border-radius: 20px;
  text-align: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  transition: all 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 50px;
  height: 50px;
  margin: 0 auto 1rem;
  border-radius: 15px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-icon svg {
  width: 24px;
  height: 24px;
}

.stat-icon.purple {
  background: #e9d8fd;
  color: #6b46c1;
}

.stat-icon.blue {
  background: #bee3f8;
  color: #2b6cb0;
}

.stat-icon.green {
  background: #c6f6d5;
  color: #276749;
}

.stat-icon.orange {
  background: #feebc8;
  color: #c05621;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: #2d3748;
}

.stat-label {
  font-size: 0.75rem;
  color: #718096;
  margin-top: 0.25rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Loading */
.loading-state {
  text-align: center;
  padding: 3rem;
  background: white;
  border-radius: 16px;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e2e8f0;
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Responsividade */
@media (max-width: 768px) {
  .admin-header {
    padding: 1.5rem;
  }

  .admin-tabs,
  .admin-users,
  .admin-stats {
    padding: 0 1rem 1rem;
  }

  .users-header {
    flex-direction: column;
    align-items: stretch;
  }

  .search-box input {
    width: 100%;
  }

  .users-table th,
  .users-table td {
    padding: 0.75rem;
  }

  .user-name-cell {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
  }

  .stats-cards {
    grid-template-columns: 1fr;
  }
}

/* Dark mode */
.dark-mode .admin-panel-page {
  background: #1a202c;
}

.dark-mode .users-table-wrapper,
.dark-mode .stat-card,
.dark-mode .tab-btn,
.dark-mode .loading-state {
  background: #2d3748;
}

.dark-mode .users-table th {
  background: #1a202c;
  color: #a0aec0;
  border-bottom-color: #4a5568;
}

.dark-mode .users-table td {
  border-bottom-color: #4a5568;
  color: #cbd5e0;
}

.dark-mode .search-box input {
  background: #2d3748;
  border-color: #4a5568;
  color: #cbd5e0;
}

.dark-mode .search-box input:focus {
  border-color: #667eea;
}

.dark-mode .current-user-badge {
  background: #4a5568;
  color: #a0aec0;
}

.dark-mode .users-header h2 {
  color: #e2e8f0;
}
</style>

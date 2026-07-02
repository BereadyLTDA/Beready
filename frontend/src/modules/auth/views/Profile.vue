<template>
  <div class="profile-container">
    <div class="profile-header">
      <div class="profile-header-background"></div>
      <div class="profile-content">
        <div class="profile-avatar">
          <div class="avatar-placeholder">
            <img
              v-if="user?.foto_perfil"
              :src="user.foto_perfil"
              alt="Foto de perfil"
              class="profile-avatar-image"
            />
            <svg
              v-else
              xmlns="http://www.w3.org/2000/svg"
              class="h-8 w-8"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
              />
            </svg>
          </div>
        </div>
        <div class="profile-info">
          <h1 class="profile-name">{{ user?.nome || 'Carregando...' }}</h1>
          <p class="profile-email">{{ user?.email || '' }}</p>
        </div>
        <div class="profile-actions">
          <button class="btn-profile btn-outline" @click="$router.push('/profile/edit')">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
              />
            </svg>
            Editar Perfil
          </button>
          <button class="btn-profile btn-danger" @click="showDeleteModal = true">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
              />
            </svg>
            Excluir Conta
          </button>
          <button class="btn-profile btn-secondary" @click="$router.push('/dashboard')">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
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
        </div>
      </div>
    </div>

    <div class="profile-body">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="profile-card">
          <div class="card-header">
            <h3 class="card-title">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                />
              </svg>
              Informações Pessoais
            </h3>
          </div>
          <div class="card-body">
            <div class="profile-info-group">
              <label class="profile-info-label">Nome Completo</label>
              <p class="profile-info-value">{{ user?.nome || '-' }}</p>
            </div>
            <div class="profile-info-group">
              <label class="profile-info-label">E-mail</label>
              <p class="profile-info-value">{{ user?.email || '-' }}</p>
            </div>
            <div class="profile-info-group">
              <label class="profile-info-label">Telefone</label>
              <p class="profile-info-value">{{ formattedPhone || 'Não informado' }}</p>
            </div>
          </div>
        </div>

        <div class="profile-card">
          <div class="card-header">
            <h3 class="card-title">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                />
              </svg>
              Preferências de Aprendizado
            </h3>
          </div>
          <div class="card-body">
            <div class="profile-info-group">
              <label class="profile-info-label">Nível de Inglês</label>
              <p class="profile-info-value">{{ getNivelIngles(user?.nivel_ingles) }}</p>
            </div>
            <div class="profile-info-group">
              <label class="profile-info-label">Idioma Preferido</label>
              <p class="profile-info-value">{{ getIdiomaPreferido(user?.idioma_preferido) }}</p>
            </div>
            <div class="profile-info-group">
              <label class="profile-info-label">Status</label>
              <p class="profile-info-value">
                <span
                  class="profile-status-badge"
                  :class="
                    user?.status === 'ativo' ? 'profile-status-active' : 'profile-status-inactive'
                  "
                >
                  {{ user?.status === 'ativo' ? 'Ativo' : 'Inativo' }}
                </span>
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="profile-card profile-mt-6">
        <div class="card-header">
          <h3 class="card-title">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-5 w-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
              />
            </svg>
            Objetivos de Aprendizado
          </h3>
        </div>
        <div class="card-body">
          <p class="profile-info-value profile-objectives-text">
            {{ user?.objetivos_aprendizado || 'Nenhum objetivo definido' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Modal de Confirmação -->
    <div v-if="showDeleteModal" class="modal-overlay" @click.self="showDeleteModal = false">
      <div class="modal-container">
        <div class="modal-header">
          <div class="modal-icon danger">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-6 w-6"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
              />
            </svg>
          </div>
          <h3 class="modal-title">Excluir Conta</h3>
        </div>
        <div class="modal-body">
          <p>Tem certeza que deseja excluir sua conta?</p>
          <p class="modal-warning">
            Esta ação é irreversível e todos os seus dados serão perdidos.
          </p>
          <div class="modal-confirm-input">
            <label
              >Digite <strong>{{ user?.email }}</strong> para confirmar:</label
            >
            <input
              v-model="confirmEmail"
              type="email"
              :placeholder="user?.email"
              class="confirm-input"
            />
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showDeleteModal = false" class="modal-btn-cancel">Cancelar</button>
          <button
            @click="handleDeleteAccount"
            :disabled="confirmEmail !== user?.email || deleteLoading"
            class="modal-btn-delete"
          >
            {{ deleteLoading ? 'Excluindo...' : 'Sim, excluir minha conta' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useProfile } from './useProfile'

const {
  user,
  formattedPhone,
  showDeleteModal,
  confirmEmail,
  deleteLoading,
  getNivelIngles,
  getIdiomaPreferido,
  handleDeleteAccount,
} = useProfile()
</script>

<style scoped>
@import '@/styles/views/users/profile.css';
</style>

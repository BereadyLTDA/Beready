<template>
  <PageContainer size="xl" class="admin-panel">
    <PageHeader
      :title="$t('admin.title')"
      :subtitle="$t('admin.welcome', { name: user?.nome })"
      icon="shield-check"
      back-to="/dashboard"
    >
      <BaseBadge variant="primary" icon="shield-check" class="admin-panel__chip mt-3">{{ $t('admin.badge') }}</BaseBadge>
    </PageHeader>

    <BaseTabs :model-value="activeTab" :tabs="tabs" :label="$t('admin.title')" @update:model-value="setTab">
      <!-- Usuários -->
      <template #users>
        <BaseCard padding="none" class="admin-panel__users">
          <template #header>
            <div class="admin-panel__users-header flex border-0 border-b border-solid border-border flex-wrap items-center justify-between gap-3 p-4 md:px-6 md:py-5">
              <h2 class="admin-panel__section-title text-xl font-semibold text-text">{{ $t('admin.manageUsers') }}</h2>
              <BaseInput
                v-model="searchQuery"
                type="search"
                icon="search"
                class="admin-panel__search max-w-96 grow basis-64"
                :placeholder="$t('admin.searchPlaceholder')"
                :aria-label="$t('admin.searchPlaceholder')"
              />
            </div>
          </template>

          <BaseSpinner v-if="loadingUsers" center show-label :label="$t('admin.loadingUsers')" />

          <EmptyState v-else-if="filteredUsers.length === 0" compact icon="users" title-tag="h3" :title="$t('common.empty')" />

          <!-- < 768px: cada linha vira um card empilhado com rótulo por célula; >= 768px: tabela -->
          <BaseTable
            v-else
            :columns="userColumns"
            :rows="filteredUsers"
            row-key="id"
            :caption="$t('admin.manageUsers')"
            caption-hidden
            min-width="44rem"
          >
            <template #cell-id="{ row }">
              <span class="admin-panel__user-id font-mono text-sm text-text-muted">#{{ row.id }}</span>
            </template>
            <template #cell-nome="{ row }">
              <div class="admin-panel__user flex min-w-0 items-center gap-3">
                <span class="admin-panel__avatar inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-primary-soft font-bold uppercase text-primary-soft-text" aria-hidden="true">{{ row.nome?.charAt(0) || 'U' }}</span>
                <span class="admin-panel__user-name wrap-anywhere font-semibold">{{ row.nome }}</span>
              </div>
            </template>
            <template #cell-email="{ row }">
              <span class="admin-panel__email min-w-0 wrap-anywhere text-text-muted">{{ row.email }}</span>
            </template>
            <template #cell-role="{ row }">
              <BaseBadge
                :variant="row.role === 'admin' ? 'primary' : 'neutral'"
                :icon="row.role === 'admin' ? 'shield-check' : 'user'"
              >
                {{ row.role === 'admin' ? $t('admin.admin') : $t('admin.user') }}
              </BaseBadge>
            </template>
            <template #cell-status="{ row }">
              <BaseBadge
                :variant="row.status === 'ativo' ? 'success' : 'neutral'"
                :icon="row.status === 'ativo' ? 'check-circle' : 'x-circle'"
              >
                {{ row.status === 'ativo' ? $t('admin.active') : $t('admin.inactive') }}
              </BaseBadge>
            </template>
            <template #cell-actions="{ row }">
              <BaseButton
                v-if="row.id !== currentUserId"
                :variant="row.role === 'admin' ? 'secondary' : 'primary'"
                :loading="updatingRole === row.id"
                @click="toggleRole(row)"
              >
                {{ row.role === 'admin' ? $t('admin.demote') : $t('admin.promote') }}
              </BaseButton>
              <BaseBadge v-else icon="user">{{ $t('admin.you') }}</BaseBadge>
            </template>
          </BaseTable>
        </BaseCard>
      </template>

      <!-- Roles -->
      <template #roles>
        <RoleManager />
      </template>

      <!-- Planos -->
      <template #planos>
        <PlanoManager />
      </template>

      <!-- Estatísticas -->
      <template #stats>
        <h2 class="sr-only">{{ $t('admin.statistics') }}</h2>
        <div class="admin-panel__stats grid grid-cols-fill-56 gap-4">
          <StatCard :label="$t('admin.totalUsers')" :value="stats.total_users || 0" icon="users">
            <template #hint>
              <span class="admin-panel__stat-split mt-1 flex flex-wrap gap-2">
                <BaseBadge size="sm" variant="primary" icon="shield-check">{{ $t('admin.adminCount') }}: {{ stats.admin_count || 0 }}</BaseBadge>
                <BaseBadge size="sm" icon="user">{{ $t('admin.userCount') }}: {{ stats.user_count || 0 }}</BaseBadge>
              </span>
            </template>
          </StatCard>
          <StatCard :label="$t('admin.flashcards')" :value="stats.total_flashcards || 0" icon="document" variant="info" />
          <StatCard :label="$t('admin.quizes')" :value="stats.total_quizes || 0" icon="clipboard" variant="success" />
          <StatCard :label="$t('admin.promptsIA')" :value="stats.total_prompts || 0" icon="chat" variant="warning" />
          <StatCard :label="$t('admin.tags')" :value="stats.total_tags || 0" icon="tag" variant="primary" />
          <StatCard :label="$t('admin.traducoes')" :value="stats.total_traducoes || 0" icon="language" variant="info" />
          <StatCard :label="$t('admin.imagensGeradas')" :value="stats.total_imagens || 0" icon="photo" variant="success" />
          <StatCard :label="$t('admin.frasesSemelhantes')" :value="stats.total_frases || 0" icon="book-open" variant="warning" />
        </div>
      </template>
    </BaseTabs>
  </PageContainer>
</template>

<script setup lang="ts">
import { ref, computed, defineAsyncComponent, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAdminPanel } from './AdminPanel'
import {
  BaseBadge,
  BaseButton,
  BaseCard,
  BaseInput,
  BaseSpinner,
  BaseTable,
  BaseTabs,
  EmptyState,
  PageContainer,
  PageHeader,
  StatCard,
  type TabItem,
  type TableColumn,
} from '@/shared/components/ui'

// Abas pesadas carregam só quando abertas (a aba padrão é a de usuários)
const RoleManager = defineAsyncComponent(() => import('@/components/admin/RoleManager.vue'))
const PlanoManager = defineAsyncComponent(() => import('@/components/admin/PlanoManager.vue'))

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const {
  user,
  loadingUsers,
  updatingRole,
  searchQuery,
  stats,
  currentUserId,
  filteredUsers,
  toggleRole,
} = useAdminPanel()

const activeTab = ref('users')

const tabs = computed<TabItem[]>(() => [
  { id: 'users', icon: 'users', label: t('admin.users') },
  { id: 'roles', icon: 'shield-check', label: t('admin.roles.title') },
  { id: 'planos', icon: 'star', label: t('admin.planos.title') },
  { id: 'stats', icon: 'chart-bar', label: t('admin.statistics') },
])

const userColumns = computed<TableColumn[]>(() => [
  { key: 'id', label: t('admin.id') },
  { key: 'nome', label: t('admin.user') },
  { key: 'email', label: t('login.email') },
  { key: 'role', label: t('admin.level') },
  { key: 'status', label: t('admin.status') },
  { key: 'actions', label: t('admin.actions') },
])

const setTab = (tab: string) => {
  activeTab.value = tab
  router.replace({ query: { tab } })
}

onMounted(() => {
  const tab = route.query.tab as string
  if (['users', 'roles', 'planos', 'stats'].includes(tab)) {
    activeTab.value = tab
  }
})

watch(() => route.query.tab, (newTab) => {
  if (['users', 'roles', 'planos', 'stats'].includes(newTab as string)) {
    activeTab.value = newTab as string
  }
})
</script>

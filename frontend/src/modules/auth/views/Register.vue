<template>
  <div class="register-page">
    <div class="register-container">
      <div class="register-card">
        <div class="register-header">
          <h1 class="register-title">Criar Conta</h1>
          <p class="register-subtitle">Junte-se à nossa comunidade de aprendizado</p>
        </div>

        <form @submit.prevent="handleSubmit">
          <div class="register-form-grid">
            <!-- Seção 1: Informações Pessoais -->
            <div class="register-section">
              <h2 class="register-section-title">Informações Pessoais</h2>
              <Input
                v-model="form.nome"
                label="Nome Completo *"
                placeholder="Seu nome completo"
                required
                :error="errors.nome"
              />
              <Input
                v-model="form.email"
                label="E-mail *"
                type="email"
                placeholder="seu.email@exemplo.com"
                required
                :error="errors.email"
              />
              <Input
                v-model="form.telefone"
                label="Telefone"
                type="tel"
                placeholder="(99) 99999-9999"
                :error="phoneError"
                @input="formatPhone"
              />
            </div>

            <!-- Seção 2: Segurança -->
            <div class="register-section">
              <h2 class="register-section-title">Segurança</h2>
              <Input
                v-model="form.senha"
                label="Senha *"
                type="password"
                placeholder="Mínimo 6 caracteres"
                required
                :error="errors.senha"
                @input="checkPasswordStrength"
              />
              <div v-if="form.senha" class="register-password-strength">
                <div class="register-strength-bar">
                  <div
                    class="register-strength-fill"
                    :class="strengthClass"
                    :style="{ width: strengthWidth }"
                  ></div>
                </div>
                <span class="register-strength-text">{{ strengthText }}</span>
              </div>
              <Input
                v-model="form.confirmar_senha"
                label="Confirmar Senha *"
                type="password"
                placeholder="Digite a senha novamente"
                required
                :error="errors.confirmar_senha"
                @input="checkPasswordMatch"
              />
              <div
                v-if="form.confirmar_senha"
                class="register-password-match"
                :class="{ matching: passwordsMatch, 'not-matching': !passwordsMatch }"
              >
                <span>{{
                  passwordsMatch ? 'As senhas coincidem' : 'As senhas não coincidem'
                }}</span>
              </div>
            </div>

            <!-- Seção 3: Preferências -->
            <div class="register-section">
              <h2 class="register-section-title">Preferências de Aprendizado</h2>
              <Select
                v-model="form.nivel_ingles"
                label="Nível de Inglês"
                placeholder="Selecione seu nível"
                :options="nivelOptions"
              />
              <Select
                v-model="form.idioma_preferido"
                label="Idioma Preferido"
                placeholder="Selecione o idioma"
                :options="idiomaOptions"
              />
              <Textarea
                v-model="form.objetivos_aprendizado"
                label="Objetivos de Aprendizado"
                placeholder="Descreva seus objetivos..."
                :rows="3"
              />
            </div>
          </div>

          <div class="register-form-actions">
            <Button variant="secondary" type="button" @click="$router.push('/login')"
              >Cancelar</Button
            >
            <Button type="submit" :loading="loading">Criar Minha Conta</Button>
          </div>
        </form>

        <div class="register-login-redirect">
          <p>Já tem uma conta? <router-link to="/login">Fazer Login</router-link></p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import Input from '@/shared/components/common/Input.vue'
import Select from '@/shared/components/common/Select.vue'
import Textarea from '@/shared/components/common/Textarea.vue'
import Button from '@/shared/components/common/Button.vue'
import { useAuthStore } from '@/stores/auth'
import { useAlert } from '@/shared/composables/useAlert'

const authStore = useAuthStore()
const { showAlert } = useAlert()

const form = reactive({
  nome: '',
  email: '',
  telefone: '',
  senha: '',
  confirmar_senha: '',
  nivel_ingles: '',
  idioma_preferido: '',
  objetivos_aprendizado: '',
})

const errors = reactive({
  nome: '',
  email: '',
  senha: '',
  confirmar_senha: '',
})

const phoneError = ref('')
const loading = ref(false)
const passwordsMatch = ref(true)

// Opções para selects
const nivelOptions = [
  { value: 'iniciante', label: 'Iniciante' },
  { value: 'intermediario', label: 'Intermediário' },
  { value: 'avancado', label: 'Avançado' },
]
const idiomaOptions = [
  { value: 'pt-BR', label: 'Português (Brasil)' },
  { value: 'en', label: 'Inglês' },
  { value: 'es', label: 'Espanhol' },
]

// Validações
const validateForm = () => {
  let valid = true
  if (!form.nome.trim()) {
    errors.nome = 'Nome é obrigatório'
    valid = false
  } else errors.nome = ''

  if (!form.email.trim()) {
    errors.email = 'E-mail é obrigatório'
    valid = false
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
    errors.email = 'E-mail inválido'
    valid = false
  } else errors.email = ''

  if (!form.senha) {
    errors.senha = 'Senha é obrigatória'
    valid = false
  } else if (form.senha.length < 6) {
    errors.senha = 'Mínimo 6 caracteres'
    valid = false
  } else errors.senha = ''

  if (form.senha !== form.confirmar_senha) {
    errors.confirmar_senha = 'As senhas não coincidem'
    valid = false
  } else errors.confirmar_senha = ''

  return valid
}

const formatPhone = (e: Event) => {
  let value = (e.target as HTMLInputElement).value.replace(/\D/g, '')
  if (value.length > 11) value = value.slice(0, 11)
  if (value.length > 10) {
    form.telefone = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3')
  } else if (value.length > 6) {
    form.telefone = value.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3')
  } else if (value.length > 2) {
    form.telefone = value.replace(/(\d{2})(\d{0,5})/, '($1) $2')
  } else {
    form.telefone = value
  }
  phoneError.value =
    form.telefone.length > 0 && form.telefone.length < 14 ? 'Telefone incompleto' : ''
}

// Força da senha
const strengthClass = ref('')
const strengthText = ref('')
const strengthWidth = ref('0%')
const checkPasswordStrength = () => {
  const pass = form.senha
  let strength = 0
  if (pass.length >= 6) strength++
  if (pass.match(/[a-z]/) && pass.match(/[A-Z]/)) strength++
  if (pass.match(/\d/)) strength++
  if (pass.match(/[^a-zA-Z0-9]/)) strength++
  if (strength === 0) {
    strengthClass.value = ''
    strengthText.value = ''
    strengthWidth.value = '0%'
  } else if (strength <= 2) {
    strengthClass.value = 'weak'
    strengthText.value = 'Fraca'
    strengthWidth.value = '33%'
  } else if (strength === 3) {
    strengthClass.value = 'medium'
    strengthText.value = 'Média'
    strengthWidth.value = '66%'
  } else {
    strengthClass.value = 'strong'
    strengthText.value = 'Forte'
    strengthWidth.value = '100%'
  }
}

const checkPasswordMatch = () => {
  passwordsMatch.value = form.senha === form.confirmar_senha
  if (!passwordsMatch.value) errors.confirmar_senha = 'As senhas não coincidem'
  else errors.confirmar_senha = ''
}

const handleSubmit = async () => {
  if (!validateForm()) return
  loading.value = true
  try {
    const response = await authStore.register(form)
    if (response.success) {
      showAlert('Conta criada com sucesso! Faça login.', 'success')
      setTimeout(() => (window.location.href = '/login'), 2000)
    } else {
      showAlert(response.message || 'Erro ao cadastrar', 'error')
    }
  } catch (err: any) {
    showAlert(err.message || 'Erro de conexão', 'error')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
@import '@/styles/views/users/register.css';
</style>

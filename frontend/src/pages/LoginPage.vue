<template>
  <div class="login-wrapper">
    <div class="login-card">
      <div class="login-logo">
        <span class="logo-icon">🔐</span>
        <h1>Area Riservata</h1>
        <p class="logo-sub">Francesco Ciappa</p>
      </div>

      <form class="login-form" @submit.prevent="handleLogin" novalidate>
        <div class="field" :class="{ error: errors.username }">
          <label for="username">Username</label>
          <input
            id="username"
            v-model.trim="form.username"
            type="text"
            autocomplete="username"
            placeholder="Inserisci il tuo username"
            :disabled="loading"
            @input="clearError('username')"
          />
          <span v-if="errors.username" class="field-error">{{ errors.username }}</span>
        </div>

        <div class="field" :class="{ error: errors.password }">
          <label for="password">Password</label>
          <div class="password-wrapper">
            <input
              id="password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              placeholder="Inserisci la tua password"
              :disabled="loading"
              @input="clearError('password')"
            />
            <button
              type="button"
              class="toggle-password"
              :aria-label="showPassword ? 'Nascondi password' : 'Mostra password'"
              @click="showPassword = !showPassword"
            >
              {{ showPassword ? '🙈' : '👁️' }}
            </button>
          </div>
          <span v-if="errors.password" class="field-error">{{ errors.password }}</span>
        </div>

        <div v-if="globalError" class="alert-error" role="alert">
          {{ globalError }}
        </div>

        <button type="submit" class="btn-login" :disabled="loading">
          <span v-if="loading" class="spinner"></span>
          <span>{{ loading ? 'Accesso in corso…' : 'Accedi' }}</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const router      = useRouter();
const auth        = useAuthStore();
const loading     = ref(false);
const showPassword = ref(false);
const globalError = ref('');

const form = reactive({ username: '', password: '' });
const errors = reactive({ username: '', password: '' });

function clearError(field) {
  errors[field] = '';
  globalError.value = '';
}

function validate() {
  let valid = true;
  if (!form.username) { errors.username = 'Username obbligatorio'; valid = false; }
  if (!form.password) { errors.password = 'Password obbligatoria'; valid = false; }
  return valid;
}

async function handleLogin() {
  if (!validate()) return;
  loading.value = true;
  globalError.value = '';
  try {
    await auth.login(form.username, form.password);
    router.push('/');
  } catch (err) {
    const status = err.response?.status;
    if (status === 401) {
      globalError.value = 'Credenziali non valide. Riprova.';
    } else if (status >= 500) {
      globalError.value = 'Errore del server. Riprova più tardi.';
    } else {
      globalError.value = 'Errore di connessione. Verifica la rete.';
    }
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
/* ── Layout ────────────────────────────────────────────── */
.login-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
  padding: 1rem;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.login-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 2.5rem 2rem;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
}

/* ── Logo ──────────────────────────────────────────────── */
.login-logo {
  text-align: center;
  margin-bottom: 2rem;
}

.logo-icon {
  font-size: 3rem;
  display: block;
  margin-bottom: 0.5rem;
}

.login-logo h1 {
  margin: 0;
  font-size: 1.5rem;
  color: #1a1a2e;
  font-weight: 700;
}

.logo-sub {
  margin: 0.25rem 0 0;
  color: #6b7280;
  font-size: 0.875rem;
}

/* ── Form ──────────────────────────────────────────────── */
.login-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.field input {
  padding: 0.625rem 0.875rem;
  border: 1.5px solid #d1d5db;
  border-radius: 8px;
  font-size: 1rem;
  color: #111827;
  background: #f9fafb;
  transition: border-color 0.2s, box-shadow 0.2s;
  outline: none;
  width: 100%;
  box-sizing: border-box;
}

.field input:focus {
  border-color: #0f3460;
  box-shadow: 0 0 0 3px rgba(15, 52, 96, 0.15);
  background: #fff;
}

.field input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.field.error input {
  border-color: #ef4444;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

/* ── Password toggle ───────────────────────────────────── */
.password-wrapper {
  position: relative;
}

.password-wrapper input {
  padding-right: 2.75rem;
}

.toggle-password {
  position: absolute;
  right: 0.625rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1.1rem;
  line-height: 1;
  padding: 0.25rem;
  border-radius: 4px;
  color: #6b7280;
  transition: color 0.2s;
}

.toggle-password:hover { color: #374151; }

/* ── Button ────────────────────────────────────────────── */
.btn-login {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  background: linear-gradient(135deg, #0f3460, #1a6fb5);
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 0.75rem;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s, transform 0.1s;
  width: 100%;
  margin-top: 0.25rem;
}

.btn-login:hover:not(:disabled) {
  opacity: 0.9;
  transform: translateY(-1px);
}

.btn-login:active:not(:disabled) {
  transform: translateY(0);
}

.btn-login:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

/* ── Responsive ────────────────────────────────────────── */
@media (max-width: 480px) {
  .login-card { padding: 2rem 1.25rem; }
}
</style>

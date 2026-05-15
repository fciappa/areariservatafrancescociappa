<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>👤 Utenti</h2>
        <p class="page-sub">Gestisci gli accessi all'area riservata</p>
      </div>
      <button class="btn-primary" @click="openNew">+ Nuovo utente</button>
    </div>

    <!-- Skeleton -->
    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 4" :key="i" class="skeleton-row" />
    </div>

    <!-- Empty -->
    <div v-else-if="!users.length" class="empty-state">
      <span>👤</span>
      <p>Nessun utente trovato.</p>
    </div>

    <!-- Tabella -->
    <div v-else class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>Utente</th>
            <th>Email</th>
            <th>Ruolo</th>
            <th>Collaboratore</th>
            <th>Stato</th>
            <th>Creato il</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in users" :key="u.id" :class="{ inactive: !u.is_active }">
            <td class="name-cell">
              <span class="avatar" :class="u.role">{{ u.username.slice(0, 2).toUpperCase() }}</span>
              {{ u.username }}
            </td>
            <td>{{ u.email }}</td>
            <td>
              <span :class="['badge-role', u.role]">
                {{ u.role === 'admin' ? '🛡️ Admin' : '👥 Collaboratore' }}
              </span>
            </td>
            <td>{{ collabName(u.collaborator_id) || '—' }}</td>
            <td>
              <span :class="['badge', u.is_active ? 'active' : 'inactive']">
                {{ u.is_active ? 'Attivo' : 'Inattivo' }}
              </span>
            </td>
            <td class="mono muted">{{ formatDate(u.created_at) }}</td>
            <td class="actions">
              <button class="btn-icon" title="Cambia password" @click="openPassword(u)">🔑</button>
              <button
                class="btn-icon"
                :title="u.is_active ? 'Disattiva' : 'Riattiva'"
                @click="toggleActive(u)"
              >{{ u.is_active ? '🚫' : '✅' }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modale nuovo utente -->
    <Teleport to="body">
      <div v-if="modal.open" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
          <div class="modal-header">
            <h3>Nuovo utente</h3>
            <button class="modal-close" @click="closeModal">✕</button>
          </div>
          <form class="modal-form" @submit.prevent="saveUser">

            <div class="form-row">
              <div class="field" :class="{ error: formErrors.username }">
                <label>Username *</label>
                <input v-model.trim="form.username" type="text" placeholder="mario.rossi" autocomplete="off" />
                <span v-if="formErrors.username" class="field-error">{{ formErrors.username }}</span>
              </div>
              <div class="field" :class="{ error: formErrors.email }">
                <label>Email *</label>
                <input v-model.trim="form.email" type="email" placeholder="mario@esempio.it" />
                <span v-if="formErrors.email" class="field-error">{{ formErrors.email }}</span>
              </div>
            </div>

            <div class="field" :class="{ error: formErrors.password }">
              <label>Password *</label>
              <div class="password-wrapper">
                <input
                  v-model="form.password"
                  :type="showPwd ? 'text' : 'password'"
                  placeholder="Minimo 8 caratteri"
                  autocomplete="new-password"
                />
                <button type="button" class="toggle-pwd" @click="showPwd = !showPwd">
                  {{ showPwd ? '🙈' : '👁️' }}
                </button>
              </div>
              <span v-if="formErrors.password" class="field-error">{{ formErrors.password }}</span>
            </div>

            <div class="field">
              <label>Ruolo *</label>
              <div class="role-toggle">
                <label class="radio-card-sm" :class="{ selected: form.role === 'collaborator' }">
                  <input v-model="form.role" type="radio" value="collaborator" />
                  <span>👥 Collaboratore</span>
                </label>
                <label class="radio-card-sm" :class="{ selected: form.role === 'admin' }">
                  <input v-model="form.role" type="radio" value="admin" />
                  <span>🛡️ Admin</span>
                </label>
              </div>
            </div>

            <!-- Collega collaboratore (solo se ruolo collaboratore) -->
            <div v-if="form.role === 'collaborator'" class="field">
              <label>Collega a collaboratore</label>
              <select v-model="form.collaborator_id">
                <option value="">Nessuno (da assegnare)</option>
                <option v-for="c in collaborators" :key="c.id" :value="c.id">
                  {{ c.first_name }} {{ c.last_name }}
                </option>
              </select>
              <span class="hint">L'utente potrà vedere le proprie ore e il riepilogo mensile.</span>
            </div>

            <div v-if="saveError" class="alert-error">{{ saveError }}</div>

            <div class="modal-footer">
              <button type="button" class="btn-secondary" @click="closeModal">Annulla</button>
              <button type="submit" class="btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner" />
                {{ saving ? 'Salvataggio…' : 'Crea utente' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Modale cambio password -->
    <Teleport to="body">
      <div v-if="pwdModal.open" class="modal-overlay" @click.self="pwdModal.open = false">
        <div class="modal" style="max-width:400px">
          <div class="modal-header">
            <h3>🔑 Cambia password — {{ pwdModal.user?.username }}</h3>
            <button class="modal-close" @click="pwdModal.open = false">✕</button>
          </div>
          <form class="modal-form" @submit.prevent="savePassword">
            <div class="field" :class="{ error: pwdModal.error }">
              <label>Nuova password *</label>
              <div class="password-wrapper">
                <input
                  v-model="pwdModal.password"
                  :type="pwdModal.show ? 'text' : 'password'"
                  placeholder="Minimo 8 caratteri"
                  autocomplete="new-password"
                />
                <button type="button" class="toggle-pwd" @click="pwdModal.show = !pwdModal.show">
                  {{ pwdModal.show ? '🙈' : '👁️' }}
                </button>
              </div>
              <span v-if="pwdModal.error" class="field-error">{{ pwdModal.error }}</span>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn-secondary" @click="pwdModal.open = false">Annulla</button>
              <button type="submit" class="btn-primary" :disabled="pwdModal.saving">
                <span v-if="pwdModal.saving" class="spinner" />
                {{ pwdModal.saving ? 'Salvataggio…' : 'Aggiorna' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../services/api.js';

const users         = ref([]);
const collaborators = ref([]);
const loading       = ref(true);
const saving        = ref(false);
const saveError     = ref('');
const showPwd       = ref(false);

const modal = reactive({ open: false });
const form  = reactive({ username: '', email: '', password: '', role: 'collaborator', collaborator_id: '' });
const formErrors = reactive({ username: '', email: '', password: '' });

const pwdModal = reactive({ open: false, user: null, password: '', show: false, saving: false, error: '' });

// ── Helpers ──────────────────────────────────────────────
function collabName(id) {
  const c = collaborators.value.find(x => x.id == id);
  return c ? `${c.first_name} ${c.last_name}` : null;
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('it-IT');
}

function resetForm() {
  Object.assign(form, { username: '', email: '', password: '', role: 'collaborator', collaborator_id: '' });
  Object.assign(formErrors, { username: '', email: '', password: '' });
  saveError.value = '';
  showPwd.value   = false;
}

function validate() {
  formErrors.username = form.username ? '' : 'Campo obbligatorio';
  formErrors.email    = form.email    ? '' : 'Campo obbligatorio';
  formErrors.password = form.password.length >= 8 ? '' : 'Minimo 8 caratteri';
  return !Object.values(formErrors).some(Boolean);
}

// ── Fetch ────────────────────────────────────────────────
async function load() {
  loading.value = true;
  try {
    const [u, c] = await Promise.all([api.get('/users'), api.get('/collaborators')]);
    users.value         = u.data;
    collaborators.value = c.data;
  } finally {
    loading.value = false;
  }
}

// ── Modal ────────────────────────────────────────────────
function openNew()    { resetForm(); modal.open = true; }
function closeModal() { modal.open = false; }

// ── CRUD ─────────────────────────────────────────────────
async function saveUser() {
  if (!validate()) return;
  saving.value = true; saveError.value = '';
  try {
    await api.post('/users', {
      username:        form.username,
      email:           form.email,
      password:        form.password,
      role:            form.role,
      collaborator_id: form.collaborator_id || null,
    });
    await load();
    closeModal();
  } catch (err) {
    saveError.value = err.response?.data?.message ?? 'Errore durante la creazione.';
  } finally {
    saving.value = false;
  }
}

async function toggleActive(u) {
  const label = u.is_active ? 'Disattivare' : 'Riattivare';
  if (!confirm(`${label} l'utente "${u.username}"?`)) return;
  await api.put(`/users/${u.id}/toggle`);
  await load();
}

function openPassword(u) {
  pwdModal.user     = u;
  pwdModal.password = '';
  pwdModal.error    = '';
  pwdModal.show     = false;
  pwdModal.open     = true;
}

async function savePassword() {
  if (pwdModal.password.length < 8) { pwdModal.error = 'Minimo 8 caratteri'; return; }
  pwdModal.saving = true; pwdModal.error = '';
  try {
    await api.put(`/users/${pwdModal.user.id}/password`, { password: pwdModal.password });
    pwdModal.open = false;
  } catch (err) {
    pwdModal.error = err.response?.data?.message ?? 'Errore aggiornamento password.';
  } finally {
    pwdModal.saving = false;
  }
}

onMounted(load);
</script>

<style scoped>
.page { padding: 2rem; max-width: 1100px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap; }
.page-header h2 { font-size: 1.5rem; font-weight: 700; color: #111827; }
.page-sub { font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem; }

.table-wrapper { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.data-table th { text-align: left; padding: 0.75rem 1rem; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; border-bottom: 1px solid #e5e7eb; background: #f9fafb; }
.data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #f3f4f6; color: #374151; vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover td { background: #f9fafb; }
.data-table tbody tr.inactive td { opacity: 0.5; }

.name-cell { display: flex; align-items: center; gap: 0.625rem; font-weight: 600; color: #111827; }
.avatar { width: 2rem; height: 2rem; border-radius: 50%; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; flex-shrink: 0; }
.avatar.admin        { background: #dc2626; }
.avatar.collaborator { background: #0f3460; }

.badge-role { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
.badge-role.admin        { background: #fee2e2; color: #991b1b; }
.badge-role.collaborator { background: #dbeafe; color: #1e40af; }

.badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
.badge.active   { background: #d1fae5; color: #065f46; }
.badge.inactive { background: #f3f4f6; color: #6b7280; }

.mono  { font-family: 'Courier New', monospace; font-size: 0.82rem; }
.muted { color: #9ca3af; }

.actions { display: flex; gap: 0.25rem; }
.btn-icon { background: none; border: none; cursor: pointer; padding: 0.25rem 0.375rem; border-radius: 6px; font-size: 1rem; transition: background 0.15s; }
.btn-icon:hover { background: #f3f4f6; }

.btn-primary { display: inline-flex; align-items: center; gap: 0.375rem; background: linear-gradient(135deg, #0f3460, #1a6fb5); color: #fff; border: none; border-radius: 8px; padding: 0.6rem 1.1rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: opacity 0.2s; white-space: nowrap; }
.btn-primary:hover:not(:disabled) { opacity: 0.9; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { background: #f3f4f6; color: #374151; border: none; border-radius: 8px; padding: 0.6rem 1.1rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; }
.btn-secondary:hover { background: #e5e7eb; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 500; padding: 1rem; }
.modal { background: #fff; border-radius: 16px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid #e5e7eb; }
.modal-header h3 { font-size: 1.1rem; font-weight: 700; color: #111827; }
.modal-close { background: none; border: none; font-size: 1.1rem; cursor: pointer; color: #6b7280; padding: 0.25rem; border-radius: 4px; }
.modal-close:hover { background: #f3f4f6; }
.modal-form { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 0.5rem; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 480px) { .form-row { grid-template-columns: 1fr; } }

.field { display: flex; flex-direction: column; gap: 0.375rem; }
.field label { font-size: 0.8rem; font-weight: 600; color: #374151; }
.field input, .field select { padding: 0.5rem 0.75rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; color: #111827; background: #f9fafb; outline: none; transition: border-color 0.2s; font-family: inherit; }
.field input:focus, .field select:focus { border-color: #0f3460; background: #fff; box-shadow: 0 0 0 3px rgba(15,52,96,0.1); }
.field.error input, .field.error select { border-color: #ef4444; }
.field-error { font-size: 0.78rem; color: #ef4444; }
.hint { font-size: 0.76rem; color: #9ca3af; }

.password-wrapper { position: relative; }
.password-wrapper input { width: 100%; padding-right: 2.75rem; box-sizing: border-box; }
.toggle-pwd { position: absolute; right: 0.625rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1rem; color: #6b7280; padding: 0.2rem; border-radius: 4px; }

.role-toggle { display: flex; gap: 0.75rem; }
.radio-card-sm { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.875rem; border: 1.5px solid #e5e7eb; border-radius: 8px; cursor: pointer; font-size: 0.875rem; font-weight: 500; color: #6b7280; transition: all 0.15s; flex: 1; justify-content: center; }
.radio-card-sm.selected { border-color: #0f3460; background: #eff6ff; color: #1d4ed8; font-weight: 600; }
.radio-card-sm input { display: none; }

.alert-error { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #b91c1c; padding: 0.75rem; font-size: 0.875rem; }

.spinner { display: inline-block; width: 0.875rem; height: 0.875rem; border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.skeleton-list { display: flex; flex-direction: column; gap: 0.5rem; }
.skeleton-row { height: 3.25rem; border-radius: 8px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.2s infinite; }
@keyframes shimmer { to { background-position: -200% 0; } }

.empty-state { text-align: center; padding: 4rem 2rem; color: #9ca3af; }
.empty-state span { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; }

@media (max-width: 768px) { .page { padding: 1rem; } }
</style>

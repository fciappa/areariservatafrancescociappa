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
            <th>Referente</th>
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
                {{ roleLabel(u.role) }}
              </span>
            </td>
            <td>{{ collabName(u.collaborator_id) || '—' }}</td>
            <td>{{ referentName(u) || '—' }}</td>
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
                <label class="radio-card-sm" :class="{ selected: form.role === 'referent' }">
                  <input v-model="form.role" type="radio" value="referent" />
                  <span>📉 Referente</span>
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

            <div v-if="form.role === 'referent'" class="field">
              <label>Collega a referente</label>
              <select v-model="form.referent_id">
                <option value="">Nessuno (da assegnare)</option>
                <option v-for="r in referents" :key="r.id" :value="r.id">
                  {{ r.first_name }} {{ r.last_name }}
                </option>
              </select>
              <span class="hint">Il referente potrà vedere ore e scadenze dei progetti assegnati.</span>
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
const referents     = ref([]);
const loading       = ref(true);
const saving        = ref(false);
const saveError     = ref('');
const showPwd       = ref(false);

const modal = reactive({ open: false });
const form  = reactive({ username: '', email: '', password: '', role: 'collaborator', collaborator_id: '', referent_id: '' });
const formErrors = reactive({ username: '', email: '', password: '' });

const pwdModal = reactive({ open: false, user: null, password: '', show: false, saving: false, error: '' });

// ── Helpers ──────────────────────────────────────────────
function collabName(id) {
  const c = collaborators.value.find(x => x.id == id);
  return c ? `${c.first_name} ${c.last_name}` : null;
}

function referentName(u) {
  if (u.referent_first_name) return `${u.referent_first_name} ${u.referent_last_name}`;
  const r = referents.value.find(x => x.id == u.referent_id);
  return r ? `${r.first_name} ${r.last_name}` : null;
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('it-IT');
}

function roleLabel(role) {
  if (role === 'admin') return '🛡️ Admin';
  if (role === 'referent') return '📉 Referente';
  return '👥 Collaboratore';
}

function resetForm() {
  Object.assign(form, { username: '', email: '', password: '', role: 'collaborator', collaborator_id: '', referent_id: '' });
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
    const [u, c, r] = await Promise.all([api.get('/users'), api.get('/collaborators'), api.get('/referents')]);
    users.value         = u.data;
    collaborators.value = c.data;
    referents.value     = r.data.filter(x => x.is_active);
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
      collaborator_id: form.role === 'collaborator' ? (form.collaborator_id || null) : null,
      referent_id:     form.role === 'referent' ? (form.referent_id || null) : null,
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
.page { max-width: 1100px; }

.data-table tbody tr.inactive td { opacity: 0.5; }

.name-cell { display: flex; align-items: center; gap: 0.625rem; font-weight: 600; color: #111827; }
.avatar { width: 2rem; height: 2rem; border-radius: 50%; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; flex-shrink: 0; }
.avatar.admin        { background: #dc2626; }
.avatar.referent     { background: #7c3aed; }
.avatar.collaborator { background: #0f3460; }

.badge-role { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
.badge-role.admin        { background: #fee2e2; color: #991b1b; }
.badge-role.referent     { background: #ede9fe; color: #5b21b6; }
.badge-role.collaborator { background: #dbeafe; color: #1e40af; }

.mono  { font-size: 0.82rem; }

.hint { font-size: 0.76rem; color: #9ca3af; }

.password-wrapper { position: relative; }
.password-wrapper input { width: 100%; padding-right: 2.75rem; box-sizing: border-box; }
.toggle-pwd { position: absolute; right: 0.625rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1rem; color: #6b7280; padding: 0.2rem; border-radius: 4px; }

.role-toggle { display: flex; gap: 0.75rem; }
.radio-card-sm { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.875rem; border: 1.5px solid #e5e7eb; border-radius: 8px; cursor: pointer; font-size: 0.875rem; font-weight: 500; color: #6b7280; transition: all 0.15s; flex: 1; justify-content: center; }
.radio-card-sm.selected { border-color: #0f3460; background: #eff6ff; color: #1d4ed8; font-weight: 600; }
.radio-card-sm input { display: none; }

</style>

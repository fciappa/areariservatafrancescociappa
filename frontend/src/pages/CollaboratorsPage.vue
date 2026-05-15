<template>
  <div class="page">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h2>👥 Collaboratori</h2>
        <p class="page-sub">Gestisci i tuoi collaboratori</p>
      </div>
      <button class="btn-primary" @click="openNew">+ Nuovo collaboratore</button>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <input
        v-model="search"
        class="search-input"
        type="search"
        placeholder="Cerca per nome, email, codice fiscale…"
      />
      <label class="toggle-inactive">
        <input v-model="showInactive" type="checkbox" />
        Mostra inattivi
      </label>
    </div>

    <!-- Skeleton -->
    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 5" :key="i" class="skeleton-row" />
    </div>

    <!-- Empty -->
    <div v-else-if="!filtered.length" class="empty-state">
      <span>😶</span>
      <p>Nessun collaboratore trovato.</p>
    </div>

    <!-- Tabella -->
    <div v-else class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>Nome</th>
            <th>Email</th>
            <th>Telefono</th>
            <th>Cod. Fiscale</th>
            <th>Stato</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="c in filtered"
            :key="c.id"
            :class="{ inactive: !c.is_active }"
          >
            <td class="name-cell">
              <span class="avatar">{{ initials(c) }}</span>
              {{ c.first_name }} {{ c.last_name }}
            </td>
            <td>{{ c.email }}</td>
            <td>{{ c.phone || '—' }}</td>
            <td class="mono">{{ c.fiscal_code || '—' }}</td>
            <td>
              <span :class="['badge', c.is_active ? 'active' : 'inactive']">
                {{ c.is_active ? 'Attivo' : 'Inattivo' }}
              </span>
            </td>
            <td class="actions">
              <button class="btn-icon" title="Modifica" @click="openEdit(c)">✏️</button>
              <button
                class="btn-icon"
                :title="c.is_active ? 'Disattiva' : 'Riattiva'"
                @click="toggleActive(c)"
              >{{ c.is_active ? '🚫' : '✅' }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modale -->
    <Teleport to="body">
      <div v-if="modal.open" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
          <div class="modal-header">
            <h3>{{ modal.isNew ? 'Nuovo collaboratore' : 'Modifica collaboratore' }}</h3>
            <button class="modal-close" @click="closeModal">✕</button>
          </div>

          <form class="modal-form" @submit.prevent="save">
            <div class="form-row">
              <div class="field" :class="{ error: formErrors.first_name }">
                <label>Nome *</label>
                <input v-model.trim="form.first_name" type="text" placeholder="Mario" />
                <span v-if="formErrors.first_name" class="field-error">{{ formErrors.first_name }}</span>
              </div>
              <div class="field" :class="{ error: formErrors.last_name }">
                <label>Cognome *</label>
                <input v-model.trim="form.last_name" type="text" placeholder="Rossi" />
                <span v-if="formErrors.last_name" class="field-error">{{ formErrors.last_name }}</span>
              </div>
            </div>

            <div class="field" :class="{ error: formErrors.email }">
              <label>Email *</label>
              <input v-model.trim="form.email" type="email" placeholder="mario@esempio.it" />
              <span v-if="formErrors.email" class="field-error">{{ formErrors.email }}</span>
            </div>

            <div class="form-row">
              <div class="field">
                <label>Telefono</label>
                <input v-model.trim="form.phone" type="tel" placeholder="+39 333 1234567" />
              </div>
              <div class="field">
                <label>Codice fiscale</label>
                <input v-model.trim="form.fiscal_code" type="text" placeholder="RSSMRA80A01H501Z" maxlength="16" style="text-transform:uppercase" />
              </div>
            </div>

            <div class="field">
              <label>Note</label>
              <textarea v-model="form.notes" rows="3" placeholder="Note aggiuntive…" />
            </div>

            <div v-if="!modal.isNew" class="field field-inline">
              <label>Attivo</label>
              <input v-model="form.is_active" type="checkbox" />
            </div>

            <div v-if="saveError" class="alert-error">{{ saveError }}</div>

            <div class="modal-footer">
              <button type="button" class="btn-secondary" @click="closeModal">Annulla</button>
              <button type="submit" class="btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner" />
                {{ saving ? 'Salvataggio…' : 'Salva' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '../services/api.js';

// ── State ────────────────────────────────────────────────
const collaborators = ref([]);
const loading       = ref(true);
const search        = ref('');
const showInactive  = ref(false);
const saving        = ref(false);
const saveError     = ref('');

const modal = reactive({ open: false, isNew: true });
const form  = reactive({ first_name: '', last_name: '', email: '', phone: '', fiscal_code: '', notes: '', is_active: true });
const formErrors = reactive({ first_name: '', last_name: '', email: '' });

// ── Computed ─────────────────────────────────────────────
const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return collaborators.value.filter(c => {
    if (!showInactive.value && !c.is_active) return false;
    if (!q) return true;
    return (
      `${c.first_name} ${c.last_name}`.toLowerCase().includes(q) ||
      (c.email        ?? '').toLowerCase().includes(q)            ||
      (c.fiscal_code  ?? '').toLowerCase().includes(q)
    );
  });
});

// ── Helpers ──────────────────────────────────────────────
function initials(c) {
  return ((c.first_name[0] ?? '') + (c.last_name[0] ?? '')).toUpperCase();
}

function resetForm() {
  Object.assign(form, { first_name: '', last_name: '', email: '', phone: '', fiscal_code: '', notes: '', is_active: true });
  Object.assign(formErrors, { first_name: '', last_name: '', email: '' });
  saveError.value = '';
}

function validate() {
  let ok = true;
  formErrors.first_name = form.first_name ? '' : 'Campo obbligatorio';
  formErrors.last_name  = form.last_name  ? '' : 'Campo obbligatorio';
  formErrors.email      = form.email      ? '' : 'Campo obbligatorio';
  if (formErrors.first_name || formErrors.last_name || formErrors.email) ok = false;
  return ok;
}

// ── Fetch ────────────────────────────────────────────────
async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/collaborators');
    collaborators.value = data;
  } finally {
    loading.value = false;
  }
}

// ── Modal ────────────────────────────────────────────────
function openNew() {
  resetForm();
  modal.isNew = true;
  modal.open  = true;
  modal._id   = null;
}

function openEdit(c) {
  resetForm();
  Object.assign(form, {
    first_name:  c.first_name,
    last_name:   c.last_name,
    email:       c.email,
    phone:       c.phone       ?? '',
    fiscal_code: c.fiscal_code ?? '',
    notes:       c.notes       ?? '',
    is_active:   Boolean(c.is_active),
  });
  modal.isNew = false;
  modal.open  = true;
  modal._id   = c.id;
}

function closeModal() {
  modal.open = false;
}

// ── CRUD ─────────────────────────────────────────────────
async function save() {
  if (!validate()) return;
  saving.value    = true;
  saveError.value = '';
  try {
    if (modal.isNew) {
      await api.post('/collaborators', form);
    } else {
      await api.put(`/collaborators/${modal._id}`, form);
    }
    await load();
    closeModal();
  } catch (err) {
    saveError.value = err.response?.data?.message ?? 'Errore durante il salvataggio.';
  } finally {
    saving.value = false;
  }
}

async function toggleActive(c) {
  const label = c.is_active ? 'Disattivare' : 'Riattivare';
  if (!confirm(`${label} ${c.first_name} ${c.last_name}?`)) return;
  await api.put(`/collaborators/${c.id}`, { ...c, is_active: !c.is_active });
  await load();
}

onMounted(load);
</script>

<style scoped>
.page { padding: 2rem; max-width: 1100px; }

/* ── Header ────────────────────────────────────────────── */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
  gap: 1rem;
  flex-wrap: wrap;
}

.page-header h2 { font-size: 1.5rem; font-weight: 700; color: #111827; }
.page-sub       { font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem; }

/* ── Toolbar ───────────────────────────────────────────── */
.toolbar {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}

.search-input {
  flex: 1;
  min-width: 220px;
  padding: 0.5rem 0.875rem;
  border: 1.5px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.9rem;
  outline: none;
  background: #fff;
}

.search-input:focus { border-color: #0f3460; box-shadow: 0 0 0 3px rgba(15,52,96,0.1); }

.toggle-inactive {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.875rem;
  color: #4b5563;
  cursor: pointer;
  white-space: nowrap;
}

/* ── Table ─────────────────────────────────────────────── */
.table-wrapper {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.data-table th {
  text-align: left;
  padding: 0.75rem 1rem;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
  border-bottom: 1px solid #e5e7eb;
  background: #f9fafb;
}

.data-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #f3f4f6;
  color: #374151;
  vertical-align: middle;
}

.data-table tbody tr:last-child td { border-bottom: none; }

.data-table tbody tr:hover td { background: #f9fafb; }

.data-table tbody tr.inactive td { opacity: 0.5; }

.name-cell {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  font-weight: 600;
  color: #111827;
}

.avatar {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: #0f3460;
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 700;
  flex-shrink: 0;
}

.mono { font-family: 'Courier New', monospace; font-size: 0.8rem; }

.badge {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.badge.active   { background: #d1fae5; color: #065f46; }
.badge.inactive { background: #f3f4f6; color: #6b7280; }

.actions { display: flex; gap: 0.25rem; }

.btn-icon {
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.25rem 0.375rem;
  border-radius: 6px;
  font-size: 1rem;
  transition: background 0.15s;
}

.btn-icon:hover { background: #f3f4f6; }

/* ── Buttons ───────────────────────────────────────────── */
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  background: linear-gradient(135deg, #0f3460, #1a6fb5);
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 0.6rem 1.1rem;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s;
  white-space: nowrap;
}

.btn-primary:hover:not(:disabled) { opacity: 0.9; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
  border: none;
  border-radius: 8px;
  padding: 0.6rem 1.1rem;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-secondary:hover { background: #e5e7eb; }

/* ── Modal ─────────────────────────────────────────────── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 500;
  padding: 1rem;
}

.modal {
  background: #fff;
  border-radius: 16px;
  width: 100%;
  max-width: 560px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 { font-size: 1.1rem; font-weight: 700; color: #111827; }

.modal-close {
  background: none;
  border: none;
  font-size: 1.1rem;
  cursor: pointer;
  color: #6b7280;
  padding: 0.25rem;
  border-radius: 4px;
  line-height: 1;
}

.modal-close:hover { background: #f3f4f6; color: #111827; }

/* ── Form ──────────────────────────────────────────────── */
.modal-form {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

@media (max-width: 480px) {
  .form-row { grid-template-columns: 1fr; }
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.field label {
  font-size: 0.8rem;
  font-weight: 600;
  color: #374151;
}

.field input[type="text"],
.field input[type="email"],
.field input[type="tel"],
.field textarea {
  padding: 0.5rem 0.75rem;
  border: 1.5px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.9rem;
  color: #111827;
  background: #f9fafb;
  outline: none;
  transition: border-color 0.2s;
  font-family: inherit;
  resize: vertical;
}

.field input:focus,
.field textarea:focus {
  border-color: #0f3460;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(15,52,96,0.1);
}

.field.error input,
.field.error textarea { border-color: #ef4444; }

.field-error { font-size: 0.78rem; color: #ef4444; }

.field-inline {
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
}

.field-inline label { margin: 0; }

.field-inline input[type="checkbox"] {
  width: 1rem;
  height: 1rem;
  cursor: pointer;
}

.alert-error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  color: #b91c1c;
  padding: 0.75rem;
  font-size: 0.875rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding-top: 0.5rem;
}

/* ── Skeleton ──────────────────────────────────────────── */
.skeleton-list { display: flex; flex-direction: column; gap: 0.5rem; }

.skeleton-row {
  height: 3.25rem;
  border-radius: 8px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}

@keyframes shimmer { to { background-position: -200% 0; } }

/* ── Empty ─────────────────────────────────────────────── */
.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  color: #9ca3af;
}

.empty-state span { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; }

/* ── Spinner ───────────────────────────────────────────── */
.spinner {
  display: inline-block;
  width: 0.875rem;
  height: 0.875rem;
  border: 2px solid rgba(255,255,255,0.35);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 768px) {
  .page { padding: 1rem; }
}
</style>

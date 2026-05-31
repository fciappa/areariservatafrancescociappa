<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>📉 Referenti</h2>
        <p class="page-sub">Gestisci l'anagrafica referenti</p>
      </div>
      <button class="btn-primary" @click="openNew">+ Nuovo referente</button>
    </div>

    <div class="toolbar">
      <input
        v-model="search"
        class="search-input"
        type="search"
        placeholder="Cerca per nome, email, codice fiscale..."
      />
      <label class="toggle-inactive">
        <input v-model="showInactive" type="checkbox" />
        Mostra inattivi
      </label>
    </div>

    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 5" :key="i" class="skeleton-row" />
    </div>

    <div v-else-if="!filtered.length" class="empty-state">
      <span>📉</span>
      <p>Nessun referente trovato.</p>
    </div>

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
          <tr v-for="r in filtered" :key="r.id" :class="{ inactive: !r.is_active }">
            <td class="name-cell">
              <span class="avatar">{{ initials(r) }}</span>
              {{ r.first_name }} {{ r.last_name }}
            </td>
            <td>{{ r.email }}</td>
            <td>{{ r.phone || '—' }}</td>
            <td class="mono">{{ r.fiscal_code || '—' }}</td>
            <td>
              <span :class="['badge', r.is_active ? 'active' : 'inactive']">
                {{ r.is_active ? 'Attivo' : 'Inattivo' }}
              </span>
            </td>
            <td class="actions">
              <button class="btn-icon" title="Modifica" @click="openEdit(r)">✏️</button>
              <button
                class="btn-icon"
                :title="r.is_active ? 'Disattiva' : 'Riattiva'"
                @click="toggleActive(r)"
              >{{ r.is_active ? '🚫' : '✅' }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="modal.open" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
          <div class="modal-header">
            <h3>{{ modal.isNew ? 'Nuovo referente' : 'Modifica referente' }}</h3>
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
                <input v-model.trim="form.fiscal_code" type="text" maxlength="16" placeholder="RSSMRA80A01H501Z" style="text-transform:uppercase" />
              </div>
            </div>

            <div class="field">
              <label>Note</label>
              <textarea v-model="form.notes" rows="3" placeholder="Note aggiuntive..." />
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
                {{ saving ? 'Salvataggio...' : 'Salva' }}
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

const referents = ref([]);
const loading = ref(true);
const search = ref('');
const showInactive = ref(false);
const saving = ref(false);
const saveError = ref('');

const modal = reactive({ open: false, isNew: true, _id: null });
const form = reactive({ first_name: '', last_name: '', email: '', phone: '', fiscal_code: '', notes: '', is_active: true });
const formErrors = reactive({ first_name: '', last_name: '', email: '' });

const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return referents.value.filter((r) => {
    if (!showInactive.value && !r.is_active) return false;
    if (!q) return true;
    return (
      `${r.first_name} ${r.last_name}`.toLowerCase().includes(q) ||
      (r.email ?? '').toLowerCase().includes(q) ||
      (r.fiscal_code ?? '').toLowerCase().includes(q)
    );
  });
});

function initials(r) {
  return ((r.first_name[0] ?? '') + (r.last_name[0] ?? '')).toUpperCase();
}

function resetForm() {
  Object.assign(form, { first_name: '', last_name: '', email: '', phone: '', fiscal_code: '', notes: '', is_active: true });
  Object.assign(formErrors, { first_name: '', last_name: '', email: '' });
  saveError.value = '';
}

function validate() {
  formErrors.first_name = form.first_name ? '' : 'Campo obbligatorio';
  formErrors.last_name = form.last_name ? '' : 'Campo obbligatorio';
  formErrors.email = form.email ? '' : 'Campo obbligatorio';
  return !formErrors.first_name && !formErrors.last_name && !formErrors.email;
}

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/referents');
    referents.value = data;
  } finally {
    loading.value = false;
  }
}

function openNew() {
  resetForm();
  modal.isNew = true;
  modal.open = true;
  modal._id = null;
}

function openEdit(r) {
  resetForm();
  Object.assign(form, {
    first_name: r.first_name,
    last_name: r.last_name,
    email: r.email,
    phone: r.phone ?? '',
    fiscal_code: r.fiscal_code ?? '',
    notes: r.notes ?? '',
    is_active: Boolean(r.is_active),
  });
  modal.isNew = false;
  modal.open = true;
  modal._id = r.id;
}

function closeModal() {
  modal.open = false;
}

async function save() {
  if (!validate()) return;
  saving.value = true;
  saveError.value = '';
  try {
    if (modal.isNew) await api.post('/referents', form);
    else await api.put(`/referents/${modal._id}`, form);
    await load();
    closeModal();
  } catch (err) {
    saveError.value = err.response?.data?.message ?? 'Errore durante il salvataggio.';
  } finally {
    saving.value = false;
  }
}

async function toggleActive(r) {
  const label = r.is_active ? 'Disattivare' : 'Riattivare';
  if (!confirm(`${label} ${r.first_name} ${r.last_name}?`)) return;
  await api.put(`/referents/${r.id}`, { ...r, is_active: !r.is_active });
  await load();
}

onMounted(load);
</script>

<style scoped>
.page { max-width: 1100px; }

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
  background: #7c3aed;
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 700;
  flex-shrink: 0;
}

.mono { font-size: 0.82rem; }

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
</style>

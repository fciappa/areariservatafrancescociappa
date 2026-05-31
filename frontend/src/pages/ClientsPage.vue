<template>
  <div class="page">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h2>🏢 Clienti</h2>
        <p class="page-sub">Gestisci il tuo portafoglio clienti</p>
      </div>
      <button class="btn-primary" @click="openNew">+ Nuovo cliente</button>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <input
        v-model="search"
        class="search-input"
        type="search"
        placeholder="Cerca per ragione sociale, P.IVA, città…"
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
      <span>🏢</span>
      <p>Nessun cliente trovato.</p>
    </div>

    <!-- Tabella -->
    <div v-else class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>Ragione Sociale</th>
            <th>P.IVA</th>
            <th>Email</th>
            <th>Telefono</th>
            <th>Città</th>
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
              <span class="avatar">{{ initials(c.company_name) }}</span>
              {{ c.company_name }}
            </td>
            <td class="mono">{{ c.vat_number }}</td>
            <td>{{ c.email || '—' }}</td>
            <td>{{ c.phone || '—' }}</td>
            <td>{{ c.city || '—' }}</td>
            <td>
              <span :class="['badge', c.is_active ? 'active' : 'inactive']">
                {{ c.is_active ? 'Attivo' : 'Inattivo' }}
              </span>
            </td>
            <td class="actions">
              <button class="btn-icon" title="Modifica" @click="openEdit(c)">✏️</button>
              <button class="btn-icon" title="Aggiungi uno o più referenti" @click="openReferents(c)">📉</button>
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
            <h3>{{ modal.isNew ? 'Nuovo cliente' : 'Modifica cliente' }}</h3>
            <button class="modal-close" @click="closeModal">✕</button>
          </div>

          <form class="modal-form" @submit.prevent="save">
            <div class="field" :class="{ error: formErrors.company_name }">
              <label>Ragione Sociale *</label>
              <input v-model.trim="form.company_name" type="text" placeholder="Acme S.r.l." />
              <span v-if="formErrors.company_name" class="field-error">{{ formErrors.company_name }}</span>
            </div>

            <div class="field" :class="{ error: formErrors.vat_number }">
              <label>Partita IVA *</label>
              <input v-model.trim="form.vat_number" type="text" placeholder="12345678901" maxlength="11" />
              <span v-if="formErrors.vat_number" class="field-error">{{ formErrors.vat_number }}</span>
            </div>

            <div class="form-row">
              <div class="field">
                <label>Email</label>
                <input v-model.trim="form.email" type="email" placeholder="info@azienda.it" />
              </div>
              <div class="field">
                <label>Telefono</label>
                <input v-model.trim="form.phone" type="tel" placeholder="+39 02 1234567" />
              </div>
            </div>

            <div class="field">
              <label>Indirizzo</label>
              <input v-model.trim="form.address" type="text" placeholder="Via Roma, 1" />
            </div>

            <div class="form-row">
              <div class="field">
                <label>Città</label>
                <input v-model.trim="form.city" type="text" placeholder="Milano" />
              </div>
              <div class="field">
                <label>CAP</label>
                <input v-model.trim="form.postal_code" type="text" placeholder="20121" maxlength="5" />
              </div>
            </div>

            <div class="field">
              <label>Nazione</label>
              <input v-model.trim="form.country" type="text" placeholder="Italia" />
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

    <Teleport to="body">
      <div v-if="referentModal.open" class="modal-overlay" @click.self="referentModal.open = false">
        <div class="modal referents-modal">
          <div class="modal-header">
            <h3>📉 Referenti cliente — {{ referentModal.client?.company_name }}</h3>
            <button class="modal-close" @click="referentModal.open = false">✕</button>
          </div>

          <div class="modal-form">
            <div v-if="referentModal.loading" class="skeleton-list">
              <div v-for="i in 2" :key="i" class="skeleton-row" />
            </div>

            <template v-else>
              <div class="field">
                <label>Referenti assegnati</label>
                <div v-if="!referentModal.assigned.length" class="empty-mini">Nessun referente assegnato.</div>
                <table v-else class="mini-table">
                  <thead>
                    <tr>
                      <th>Utente</th>
                      <th>Anagrafica</th>
                      <th>Email</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="r in referentModal.assigned" :key="r.user_id">
                      <td class="fw">{{ r.username }}</td>
                      <td>{{ r.first_name ? `${r.first_name} ${r.last_name}` : '—' }}</td>
                      <td>{{ r.email }}</td>
                      <td>
                        <button class="btn-icon" title="Rimuovi referente" @click="removeClientReferent(r)">🗑️</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="field">
                <label>Aggiungi uno o più referenti</label>
                <div class="check-list">
                  <label v-for="u in availableReferentUsers" :key="u.id" class="check-item">
                    <input type="checkbox" :value="u.id" v-model="referentModal.selectedUserIds" />
                    <span>{{ u.username }} — {{ u.display_name }}</span>
                  </label>
                </div>
              </div>

              <div v-if="referentModal.error" class="alert-error">{{ referentModal.error }}</div>

              <div class="modal-footer">
                <button type="button" class="btn-secondary" @click="referentModal.open = false">Chiudi</button>
                <button class="btn-primary" :disabled="referentModal.saving" @click="addClientReferents">
                  <span v-if="referentModal.saving" class="spinner" />
                  {{ referentModal.saving ? 'Salvataggio…' : '+ Aggiungi selezionati' }}
                </button>
              </div>
            </template>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '../services/api.js';

// ── State ────────────────────────────────────────────────
const clients      = ref([]);
const users        = ref([]);
const referents    = ref([]);
const loading      = ref(true);
const search       = ref('');
const showInactive = ref(false);
const saving       = ref(false);
const saveError    = ref('');

const modal = reactive({ open: false, isNew: true, _id: null });
const form  = reactive({
  company_name: '', vat_number: '', email: '', phone: '',
  address: '', city: '', postal_code: '', country: 'Italia',
  notes: '', is_active: true,
});
const formErrors = reactive({ company_name: '', vat_number: '' });
const referentModal = reactive({
  open: false,
  loading: false,
  saving: false,
  error: '',
  client: null,
  assigned: [],
  selectedUserIds: [],
});

// ── Computed ─────────────────────────────────────────────
const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return clients.value.filter(c => {
    if (!showInactive.value && !c.is_active) return false;
    if (!q) return true;
    return (
      c.company_name.toLowerCase().includes(q) ||
      c.vat_number.toLowerCase().includes(q)   ||
      (c.city ?? '').toLowerCase().includes(q)
    );
  });
});

const availableReferentUsers = computed(() => {
  return users.value
    .filter(u => u.is_active && u.role === 'referent')
    .map(u => {
      const r = referents.value.find(x => x.id == u.referent_id);
      const displayName = r ? `${r.first_name} ${r.last_name}` : 'Anagrafica non collegata';
      return { ...u, display_name: displayName };
    });
});

// ── Helpers ──────────────────────────────────────────────
function initials(name) {
  return name.split(' ').map(w => w[0] ?? '').slice(0, 2).join('').toUpperCase();
}

function resetForm() {
  Object.assign(form, {
    company_name: '', vat_number: '', email: '', phone: '',
    address: '', city: '', postal_code: '', country: 'Italia',
    notes: '', is_active: true,
  });
  Object.assign(formErrors, { company_name: '', vat_number: '' });
  saveError.value = '';
}

function validate() {
  formErrors.company_name = form.company_name ? '' : 'Campo obbligatorio';
  formErrors.vat_number   = form.vat_number   ? '' : 'Campo obbligatorio';
  return !formErrors.company_name && !formErrors.vat_number;
}

// ── Fetch ────────────────────────────────────────────────
async function load() {
  loading.value = true;
  try {
    const [c, u, r] = await Promise.all([
      api.get('/clients'),
      api.get('/users'),
      api.get('/referents'),
    ]);
    clients.value = c.data;
    users.value = u.data;
    referents.value = r.data;
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
    company_name: c.company_name,
    vat_number:   c.vat_number,
    email:        c.email        ?? '',
    phone:        c.phone        ?? '',
    address:      c.address      ?? '',
    city:         c.city         ?? '',
    postal_code:  c.postal_code  ?? '',
    country:      c.country      ?? 'Italia',
    notes:        c.notes        ?? '',
    is_active:    Boolean(c.is_active),
  });
  modal.isNew = false;
  modal.open  = true;
  modal._id   = c.id;
}

function closeModal() { modal.open = false; }

// ── CRUD ─────────────────────────────────────────────────
async function save() {
  if (!validate()) return;
  saving.value    = true;
  saveError.value = '';
  try {
    if (modal.isNew) {
      await api.post('/clients', form);
    } else {
      await api.put(`/clients/${modal._id}`, form);
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
  if (!confirm(`${label} ${c.company_name}?`)) return;
  await api.put(`/clients/${c.id}`, { ...c, is_active: !c.is_active });
  await load();
}

async function openReferents(client) {
  referentModal.open = true;
  referentModal.loading = true;
  referentModal.saving = false;
  referentModal.error = '';
  referentModal.client = client;
  referentModal.selectedUserIds = [];

  try {
    const { data } = await api.get(`/clients/${client.id}/referents`);
    referentModal.assigned = data;
  } finally {
    referentModal.loading = false;
  }
}

async function addClientReferents() {
  if (!referentModal.selectedUserIds.length) {
    referentModal.error = 'Seleziona almeno un referente';
    return;
  }

  referentModal.saving = true;
  referentModal.error = '';

  try {
    await api.post(`/clients/${referentModal.client.id}/referents`, {
      user_ids: referentModal.selectedUserIds,
    });
    const { data } = await api.get(`/clients/${referentModal.client.id}/referents`);
    referentModal.assigned = data;
    referentModal.selectedUserIds = [];
    await load();
  } catch (err) {
    referentModal.error = err.response?.data?.message ?? 'Errore durante il salvataggio referenti.';
  } finally {
    referentModal.saving = false;
  }
}

async function removeClientReferent(r) {
  if (!confirm(`Rimuovere ${r.username} dai referenti di questo cliente?`)) return;
  await api.delete(`/clients/${referentModal.client.id}/referents/${r.user_id}`);
  referentModal.assigned = referentModal.assigned.filter(x => x.user_id !== r.user_id);
  await load();
}

onMounted(load);
</script>

<style scoped>
.toolbar {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}

.search-input {
  flex: 1;
  min-width: 240px;
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
  border-radius: 8px;
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

.modal { max-width: 580px; }

.field-inline {
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
}

.field-inline label { margin: 0; }

.field-inline input[type="checkbox"] { width: 1rem; height: 1rem; cursor: pointer; }

.referents-modal {
  max-width: min(900px, 96vw);
}

.mini-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.84rem;
}

.mini-table th {
  text-align: left;
  padding: 0.4rem 0.5rem;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
  border-bottom: 1px solid #e5e7eb;
}

.mini-table td {
  padding: 0.45rem 0.5rem;
  border-bottom: 1px solid #f3f4f6;
}

.check-list {
  max-height: 180px;
  overflow: auto;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 0.35rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.check-item {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  font-size: 0.86rem;
  color: #374151;
  padding: 0.35rem;
  border-radius: 6px;
}

.check-item:hover {
  background: #f9fafb;
}

.empty-mini {
  color: #9ca3af;
  font-size: 0.86rem;
  padding: 0.4rem 0;
}
</style>

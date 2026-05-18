<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>📁 Progetti</h2>
        <p class="page-sub">Gestisci i progetti e le tariffe associate</p>
      </div>
      <button class="btn-primary" @click="openNew">+ Nuovo progetto</button>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <input v-model="search" class="search-input" type="search" placeholder="Cerca per nome, cliente…" />
      <select v-model="filterStatus" class="select-input">
        <option value="">Tutti gli stati</option>
        <option value="active">Attivo</option>
        <option value="on_hold">In pausa</option>
        <option value="completed">Completato</option>
        <option value="archived">Archiviato</option>
      </select>
      <label class="toggle-inactive">
        <input v-model="showInactive" type="checkbox" />
        Mostra inattivi
      </label>
    </div>

    <!-- Skeleton -->
    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 4" :key="i" class="skeleton-row" />
    </div>

    <!-- Empty -->
    <div v-else-if="!filtered.length" class="empty-state">
      <span>📁</span>
      <p>Nessun progetto trovato.</p>
    </div>

    <!-- Cards -->
    <div v-else class="projects-grid">
      <div
        v-for="p in filtered"
        :key="p.id"
        class="project-card"
        :class="[p.status, { inactive: !p.is_active }]"
      >
        <div class="card-top">
          <div class="card-info">
            <div class="card-name">{{ p.name }}</div>
            <div class="card-client">🏢 {{ p.company_name }}</div>
          </div>
          <div class="card-actions">
            <button class="btn-icon" title="Tariffe" @click="openAssignments(p)">💰</button>
            <button class="btn-icon" title="Modifica" @click="openEdit(p)">✏️</button>
          </div>
        </div>

        <div v-if="p.description" class="card-desc">{{ p.description }}</div>

        <div class="card-meta">
          <span :class="['status-badge', p.status]">{{ statusLabel(p.status) }}</span>
          <span class="date-range">
            📅 {{ formatDate(p.start_date) }}
            <template v-if="p.end_date"> → {{ formatDate(p.end_date) }}</template>
            <template v-else> · aperto</template>
          </span>
        </div>
      </div>
    </div>

    <!-- ── Modale progetto ──────────────────────────────── -->
    <Teleport to="body">
      <div v-if="modal.open" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
          <div class="modal-header">
            <h3>{{ modal.isNew ? 'Nuovo progetto' : 'Modifica progetto' }}</h3>
            <button class="modal-close" @click="closeModal">✕</button>
          </div>
          <form class="modal-form" @submit.prevent="save">

            <div class="field" :class="{ error: formErrors.name }">
              <label>Nome progetto *</label>
              <input v-model.trim="form.name" type="text" placeholder="Es. Sviluppo portale e-commerce" />
              <span v-if="formErrors.name" class="field-error">{{ formErrors.name }}</span>
            </div>

            <div class="field" :class="{ error: formErrors.client_id }">
              <label>Cliente *</label>
              <select v-model="form.client_id">
                <option value="">Seleziona cliente…</option>
                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
              </select>
              <span v-if="formErrors.client_id" class="field-error">{{ formErrors.client_id }}</span>
            </div>

            <div class="field">
              <label>Descrizione</label>
              <textarea v-model="form.description" rows="2" placeholder="Descrizione del progetto…" />
            </div>

            <div class="field">
              <label>Stato</label>
              <select v-model="form.status">
                <option value="active">Attivo</option>
                <option value="on_hold">In pausa</option>
                <option value="completed">Completato</option>
                <option value="archived">Archiviato</option>
              </select>
            </div>

            <div class="form-row">
              <div class="field" :class="{ error: formErrors.start_date }">
                <label>Data inizio *</label>
                <input v-model="form.start_date" type="date" />
                <span v-if="formErrors.start_date" class="field-error">{{ formErrors.start_date }}</span>
              </div>
              <div class="field">
                <label>Data fine <span class="optional">(opzionale)</span></label>
                <input v-model="form.end_date" type="date" :min="form.start_date" />
              </div>
            </div>

            <div class="field">
              <label>Note</label>
              <textarea v-model="form.notes" rows="2" placeholder="Note interne…" />
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

    <!-- ── Modale assegnazioni tariffe ──────────────────── -->
    <Teleport to="body">
      <div v-if="assignModal.open" class="modal-overlay" @click.self="assignModal.open = false">
        <div class="modal modal-wide">
          <div class="modal-header">
            <h3>💰 Tariffe — {{ assignModal.project?.name }}</h3>
            <button class="modal-close" @click="assignModal.open = false">✕</button>
          </div>

          <div class="assign-body">
            <p class="assign-desc">
              Assegna una o più tariffe a questo progetto.<br/>
              Se indichi un collaboratore, quella tariffa vale <strong>solo per lui</strong>.
              Se lasci "tutti", vale per qualsiasi collaboratore senza assegnazione specifica.
            </p>

            <!-- Lista assegnazioni esistenti -->
            <div v-if="assignModal.loading" class="skeleton-list">
              <div v-for="i in 2" :key="i" class="skeleton-row" />
            </div>
            <div v-else-if="!assignModal.assignments.length" class="empty-small">
              Nessuna tariffa assegnata.
            </div>
            <table v-else class="mini-table">
              <thead>
                <tr>
                  <th>Tariffa</th>
                  <th>€/ora</th>
                  <th>4%</th>
                  <th>Collaboratore</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="a in assignModal.assignments" :key="a.id">
                  <td class="fw">{{ a.tariff_name }}</td>
                  <td class="mono">€ {{ fmt(a.hourly_rate) }}</td>
                  <td>
                    <span :class="['pill', a.tax_inclusive ? 'in' : 'ex']">
                      {{ a.tax_inclusive ? 'incl.' : 'escl.' }}
                    </span>
                  </td>
                  <td>
                    <span v-if="a.collaborator_id">{{ a.first_name }} {{ a.last_name }}</span>
                    <span v-else class="muted">⭐ tutti</span>
                  </td>
                  <td>
                    <button class="btn-icon" title="Rimuovi" @click="removeAssignment(a)">🗑️</button>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Form nuova assegnazione -->
            <div class="assign-form">
              <h4>Aggiungi assegnazione</h4>
              <div class="form-row">
                <div class="field" :class="{ error: assignErrors.tariff_id }">
                  <label>Tariffa *</label>
                  <select v-model="newAssign.tariff_id">
                    <option value="">Seleziona tariffa…</option>
                    <option v-for="t in tariffs" :key="t.id" :value="t.id">
                      {{ t.name }} — € {{ fmt(t.hourly_rate) }}/h {{ t.is_default ? '⭐' : '' }}
                    </option>
                  </select>
                  <span v-if="assignErrors.tariff_id" class="field-error">{{ assignErrors.tariff_id }}</span>
                </div>
                <div class="field">
                  <label>Collaboratore <span class="optional">(opzionale = tutti)</span></label>
                  <select v-model="newAssign.collaborator_id">
                    <option value="">⭐ Tutti i collaboratori</option>
                    <option v-for="c in collaborators" :key="c.id" :value="c.id">
                      {{ c.first_name }} {{ c.last_name }}
                    </option>
                  </select>
                </div>
              </div>
              <div v-if="assignModal.saveError" class="alert-error">{{ assignModal.saveError }}</div>
              <button class="btn-primary" :disabled="assignModal.saving" @click="addAssignment">
                <span v-if="assignModal.saving" class="spinner" />
                {{ assignModal.saving ? 'Aggiunta…' : '+ Aggiungi' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '../services/api.js';

const projects      = ref([]);
const clients       = ref([]);
const collaborators = ref([]);
const tariffs       = ref([]);
const loading       = ref(true);
const saving        = ref(false);
const saveError     = ref('');
const search        = ref('');
const filterStatus  = ref('');
const showInactive  = ref(false);

const modal = reactive({ open: false, isNew: true, _id: null });
const form  = reactive({ name: '', client_id: '', description: '', status: 'active', start_date: today(), end_date: '', notes: '', is_active: true });
const formErrors = reactive({ name: '', client_id: '', start_date: '' });

const assignModal = reactive({
  open: false, project: null, loading: false,
  assignments: [], saving: false, saveError: '',
});
const newAssign  = reactive({ tariff_id: '', collaborator_id: '' });
const assignErrors = reactive({ tariff_id: '' });

// ── Computed ─────────────────────────────────────────────
const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return projects.value.filter(p => {
    if (!showInactive.value && !p.is_active) return false;
    if (filterStatus.value && p.status !== filterStatus.value) return false;
    if (!q) return true;
    return p.name.toLowerCase().includes(q) || p.company_name.toLowerCase().includes(q);
  });
});

// ── Helpers ──────────────────────────────────────────────
function today() { return new Date().toISOString().slice(0, 10); }
function fmt(v)  { return Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function formatDate(d) { return new Date(d).toLocaleDateString('it-IT'); }
function statusLabel(s) {
  return { active: 'Attivo', on_hold: 'In pausa', completed: 'Completato', archived: 'Archiviato' }[s] ?? s;
}

function resetForm() {
  Object.assign(form, { name: '', client_id: '', description: '', status: 'active', start_date: today(), end_date: '', notes: '', is_active: true });
  Object.assign(formErrors, { name: '', client_id: '', start_date: '' });
  saveError.value = '';
}

function validate() {
  formErrors.name       = form.name       ? '' : 'Campo obbligatorio';
  formErrors.client_id  = form.client_id  ? '' : 'Seleziona un cliente';
  formErrors.start_date = form.start_date ? '' : 'Campo obbligatorio';
  return !Object.values(formErrors).some(Boolean);
}

// ── Fetch ────────────────────────────────────────────────
async function load() {
  loading.value = true;
  try {
    const [p, c, co, t] = await Promise.all([
      api.get('/projects'),
      api.get('/clients'),
      api.get('/collaborators'),
      api.get('/tariffs'),
    ]);
    projects.value      = p.data;
    clients.value       = c.data.filter(x => x.is_active);
    collaborators.value = co.data.filter(x => x.is_active);
    tariffs.value       = t.data;
  } finally {
    loading.value = false;
  }
}

// ── Modal progetto ────────────────────────────────────────
function openNew()    { resetForm(); modal.isNew = true;  modal.open = true; modal._id = null; }
function closeModal() { modal.open = false; }

function openEdit(p) {
  resetForm();
  Object.assign(form, {
    name:        p.name,
    client_id:   p.client_id,
    description: p.description ?? '',
    status:      p.status,
    start_date:  p.start_date?.slice(0, 10) ?? '',
    end_date:    p.end_date?.slice(0, 10)   ?? '',
    notes:       p.notes ?? '',
    is_active:   Boolean(p.is_active),
  });
  modal.isNew = false; modal.open = true; modal._id = p.id;
}

async function save() {
  if (!validate()) return;
  saving.value = true; saveError.value = '';
  try {
    const payload = { ...form, end_date: form.end_date || null };
    if (modal.isNew) await api.post('/projects', payload);
    else             await api.put(`/projects/${modal._id}`, payload);
    await load(); closeModal();
  } catch (err) {
    saveError.value = err.response?.data?.message ?? 'Errore durante il salvataggio.';
  } finally { saving.value = false; }
}

// ── Modal assegnazioni ────────────────────────────────────
async function openAssignments(p) {
  assignModal.project    = p;
  assignModal.open       = true;
  assignModal.loading    = true;
  assignModal.saveError  = '';
  Object.assign(newAssign, { tariff_id: '', collaborator_id: '' });
  assignErrors.tariff_id = '';
  try {
    const { data } = await api.get(`/projects/${p.id}`);
    assignModal.assignments = data.assignments;
  } finally { assignModal.loading = false; }
}

async function addAssignment() {
  if (!newAssign.tariff_id) { assignErrors.tariff_id = 'Seleziona una tariffa'; return; }
  assignErrors.tariff_id   = '';
  assignModal.saveError    = '';
  assignModal.saving       = true;
  try {
    await api.post(`/projects/${assignModal.project.id}/assignments`, {
      tariff_id:       newAssign.tariff_id,
      collaborator_id: newAssign.collaborator_id || null,
    });
    const { data } = await api.get(`/projects/${assignModal.project.id}`);
    assignModal.assignments = data.assignments;
    Object.assign(newAssign, { tariff_id: '', collaborator_id: '' });
  } catch (err) {
    assignModal.saveError = err.response?.data?.message ?? 'Errore.';
  } finally { assignModal.saving = false; }
}

async function removeAssignment(a) {
  if (!confirm('Rimuovere questa assegnazione?')) return;
  await api.delete(`/projects/assignments/${a.id}`);
  assignModal.assignments = assignModal.assignments.filter(x => x.id !== a.id);
}

onMounted(load);
</script>

<style scoped>
.toolbar { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
.search-input { flex: 1; min-width: 220px; padding: 0.5rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; outline: none; background: #fff; }
.search-input:focus { border-color: #0f3460; box-shadow: 0 0 0 3px rgba(15,52,96,0.1); }
.select-input { padding: 0.5rem 0.75rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; background: #fff; outline: none; color: #374151; }
.toggle-inactive { display: flex; align-items: center; gap: 0.375rem; font-size: 0.875rem; color: #4b5563; cursor: pointer; white-space: nowrap; }

/* ── Project cards ──────────────────────────────────────── */
.projects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; }

.project-card {
  background: #fff; border-radius: 12px; padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08); border-left: 4px solid #d1d5db;
  transition: transform 0.15s;
}
.project-card:hover { transform: translateY(-2px); }
.project-card.inactive { opacity: 0.55; }
.project-card.active    { border-color: #10b981; }
.project-card.on_hold   { border-color: #f59e0b; }
.project-card.completed { border-color: #6366f1; }
.project-card.archived  { border-color: #9ca3af; }

.card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.625rem; }
.card-name { font-size: 1rem; font-weight: 700; color: #111827; line-height: 1.3; }
.card-client { font-size: 0.8rem; color: #6b7280; margin-top: 0.125rem; }
.card-actions { display: flex; gap: 0.25rem; flex-shrink: 0; margin-left: 0.5rem; }
.card-desc { font-size: 0.8rem; color: #6b7280; margin-bottom: 0.75rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.card-meta { display: flex; align-items: center; gap: 0.625rem; flex-wrap: wrap; margin-top: 0.5rem; }

.status-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.72rem; font-weight: 700; }
.status-badge.active    { background: #d1fae5; color: #065f46; }
.status-badge.on_hold   { background: #fef3c7; color: #92400e; }
.status-badge.completed { background: #ede9fe; color: #4c1d95; }
.status-badge.archived  { background: #f3f4f6; color: #6b7280; }

.date-range { font-size: 0.78rem; color: #9ca3af; }

/* ── Modali ─────────────────────────────────────────────── */
.modal { max-width: 540px; }
.modal-wide { max-width: 680px; }

.optional { font-weight: 400; color: #9ca3af; }
.field-inline { flex-direction: row; align-items: center; gap: 0.5rem; }
.field-inline label { margin: 0; }
.field-inline input[type="checkbox"] { width: 1rem; height: 1rem; }

/* ── Assign modal ───────────────────────────────────────── */
.assign-body { padding: 1.5rem; }
.assign-desc { font-size: 0.875rem; color: #6b7280; margin-bottom: 1.25rem; line-height: 1.5; }
.assign-form { margin-top: 1.25rem; border-top: 1px solid #e5e7eb; padding-top: 1.25rem; }
.assign-form h4 { font-size: 0.9rem; font-weight: 700; color: #111827; margin-bottom: 1rem; }

.mini-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.mini-table th { text-align: left; padding: 0.375rem 0.5rem; font-size: 0.72rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #e5e7eb; }
.mini-table td { padding: 0.5rem; border-bottom: 1px solid #f3f4f6; color: #374151; }
.mini-table tbody tr:last-child td { border-bottom: none; }

.muted { font-size: 0.8rem; }
.pill  { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 9999px; font-size: 0.68rem; font-weight: 600; }
.pill.in { background: #d1fae5; color: #065f46; }
.pill.ex { background: #dbeafe; color: #1e40af; }

.alert-error { margin-top: 0.5rem; }

.empty-small { text-align: center; padding: 1.5rem; color: #9ca3af; font-size: 0.875rem; }
</style>

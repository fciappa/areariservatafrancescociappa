<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>🕐 Ore Collaboratori</h2>
        <p class="page-sub">Ore lavorate dai collaboratori per te</p>
      </div>
      <button class="btn-primary" @click="openNew">+ Inserisci ore</button>
    </div>

    <!-- Filtri -->
    <div class="toolbar">
      <select v-model="filterCollab" class="select-input">
        <option value="">Tutti i collaboratori</option>
        <option v-for="c in collaborators" :key="c.id" :value="c.id">
          {{ c.first_name }} {{ c.last_name }}
        </option>
      </select>
      <select v-model="filterProject" class="select-input">
        <option value="">Tutti i progetti</option>
        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <input v-model="filterMonth" type="month" class="select-input" />
      <button v-if="filterCollab || filterProject || filterMonth" class="btn-ghost" @click="clearFilters">✕ Pulisci</button>
    </div>

    <!-- Riepilogo -->
    <div v-if="filtered.length" class="summary-bar">
      <div class="summary-item">
        <span class="summary-label">Ore totali</span>
        <span class="summary-value">{{ totalHours }}h</span>
      </div>
      <div class="summary-item">
        <span class="summary-label">Da pagare (lordo)</span>
        <span class="summary-value green">€ {{ formatAmount(totalGross) }}</span>
      </div>
    </div>

    <!-- Skeleton -->
    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 5" :key="i" class="skeleton-row" />
    </div>

    <!-- Empty -->
    <div v-else-if="!filtered.length" class="empty-state">
      <span>🕐</span>
      <p>Nessuna ora registrata per i collaboratori.</p>
    </div>

    <!-- Tabella -->
    <div v-else class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>Data</th>
            <th>Collaboratore</th>
            <th>Tariffa</th>
            <th>Ore</th>
            <th>€/ora</th>
            <th>Lordo</th>
            <th>4%</th>
            <th>Descrizione</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="h in filtered" :key="h.id" :class="{ 'row-invoiced': h.invoiced_at }">
            <td class="mono" data-label="Data">{{ formatDate(h.work_date) }}</td>
            <td class="fw" data-label="Collaboratore">
              <span class="avatar">{{ initials(h) }}</span>
              {{ h.first_name }} {{ h.last_name }}
            </td>
            <td data-label="Tariffa">
              <span class="tariff-name">{{ h.tariff_name }}</span>
              <span :class="['pill', h.tax_inclusive ? 'in' : 'ex']">
                {{ h.tax_inclusive ? '4% incl.' : '4% escl.' }}
              </span>
              <span class="pill rate-pill">{{ h.rate_type === 'daily' ? '📅 giornaliera' : '⏱️ oraria' }}</span>
            </td>
            <td class="mono" data-label="Ore">{{ h.hours }}h</td>
            <td class="mono" data-label="€/ora">
              € {{ formatAmount(h.hourly_rate) }}
              <span class="rate-unit-small">{{ h.rate_type === 'daily' ? '/g' : '/h' }}</span>
            </td>
            <td class="mono green" data-label="Lordo">€ {{ formatAmount(calcGross(h)) }}</td>
            <td class="mono muted" data-label="4%">€ {{ formatAmount(calcTax(h)) }}</td>
            <td class="desc" data-label="Note">{{ h.description || '—' }}</td>
            <td class="actions">
              <span v-if="h.invoiced_at" class="invoiced-icon" :title="`Fatturata il ${formatDate(h.invoiced_at)}`">🧾</span>
              <button class="btn-icon" title="Duplica" @click="openDuplicate(h)">📋</button>
              <button class="btn-icon" title="Modifica" @click="openEdit(h)">✏️</button>
              <button class="btn-icon" title="Elimina" @click="remove(h)">🗑️</button>
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
            <h3>{{ modal.isNew ? 'Inserisci ore collaboratore' : 'Modifica ore' }}</h3>
            <button class="modal-close" @click="closeModal">✕</button>
          </div>
          <form class="modal-form" @submit.prevent="save">

            <div class="form-row">
              <div class="field" :class="{ error: formErrors.work_date }">
                <label>Data *</label>
                <input v-model="form.work_date" type="date" />
                <span v-if="formErrors.work_date" class="field-error">{{ formErrors.work_date }}</span>
              </div>
              <div class="field" :class="{ error: formErrors.hours }">
                <label>Ore *</label>
                <input v-model="form.hours" type="number" min="0.25" max="24" step="0.25" placeholder="8" />
                <span v-if="formErrors.hours" class="field-error">{{ formErrors.hours }}</span>
              </div>
            </div>

            <div class="field" :class="{ error: formErrors.collaborator_id }">
              <label>Collaboratore *</label>
              <select v-model="form.collaborator_id" @change="resolveTariff">
                <option value="">Seleziona collaboratore…</option>
                <option v-for="c in collaborators" :key="c.id" :value="c.id">
                  {{ c.first_name }} {{ c.last_name }}
                </option>
              </select>
              <span v-if="formErrors.collaborator_id" class="field-error">{{ formErrors.collaborator_id }}</span>
            </div>

            <div class="field" :class="{ error: formErrors.project_id }">
              <label>Progetto *</label>
              <select v-model="form.project_id" @change="resolveTariff">
                <option value="">Seleziona progetto…</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
              <span v-if="formErrors.project_id" class="field-error">{{ formErrors.project_id }}</span>
            </div>

            <div class="field" :class="{ error: formErrors.tariff_id }">
              <label>Tariffa *</label>
              <select v-model="form.tariff_id">
                <option value="">Seleziona tariffa…</option>
                <option v-for="t in tariffs" :key="t.id" :value="t.id">
                  {{ t.name }} — € {{ formatAmount(t.hourly_rate) }}{{ t.rate_type === 'daily' ? '/giorno' : '/h' }} {{ t.is_default ? '⭐' : '' }}
                </option>
              </select>
              <span v-if="formErrors.tariff_id" class="field-error">{{ formErrors.tariff_id }}</span>
              <span v-if="tariffResolved" class="hint-ok">✅ Tariffa auto-risolta dal progetto</span>
            </div>

            <!-- Preview -->
            <div v-if="selectedTariff" class="preview-box">
              <div class="preview-title">🔢 Anteprima per {{ form.hours || 0 }} ore</div>
              <div class="preview-rows">
                <div class="preview-row"><span>Lordo da pagare</span><span class="mono">€ {{ modalPreview.gross }}</span></div>
                <div class="preview-row"><span>4% ({{ selectedTariff.tax_inclusive ? 'scorporato' : 'aggiunto' }})</span><span class="mono">€ {{ modalPreview.tax }}</span></div>
              </div>
            </div>

            <div class="field">
              <label>Descrizione</label>
              <textarea v-model="form.description" rows="2" placeholder="Attività svolta…" />
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
import { ref, reactive, computed, onMounted, watch } from 'vue';
import api from '../services/api.js';

const hours         = ref([]);
const collaborators = ref([]);
const projects      = ref([]);
const tariffs       = ref([]);
const loading       = ref(true);
const saving        = ref(false);
const saveError     = ref('');
const filterCollab  = ref('');
const filterProject = ref('');
const filterMonth   = ref('');
const tariffResolved = ref(false);

const modal = reactive({ open: false, isNew: true, _id: null });
const form  = reactive({ work_date: today(), hours: '', collaborator_id: '', project_id: '', tariff_id: '', description: '' });
const formErrors = reactive({ work_date: '', hours: '', collaborator_id: '', project_id: '', tariff_id: '' });

const filtered = computed(() =>
  hours.value.filter(h => {
    if (filterCollab.value   && h.collaborator_id != filterCollab.value)  return false;
    if (filterProject.value  && h.project_id       != filterProject.value) return false;
    if (filterMonth.value    && !h.work_date.slice(0, 7).startsWith(filterMonth.value)) return false;
    return true;
  })
);

const selectedTariff = computed(() => tariffs.value.find(t => t.id == form.tariff_id) ?? null);

const modalPreview = computed(() => {
  const t = selectedTariff.value;
  if (!t) return {};
  const effective = t.rate_type === 'daily' ? parseFloat(t.hourly_rate) / 8 : parseFloat(t.hourly_rate);
  const gross = effective * (parseFloat(form.hours) || 0);
  const tax   = t.tax_inclusive ? gross - gross / 1.04 : gross * 0.04;
  return { gross: fmt(gross), tax: fmt(tax) };
});

const totalHours = computed(() => filtered.value.reduce((s, h) => s + parseFloat(h.hours), 0));
const totalGross = computed(() => filtered.value.reduce((s, h) => s + calcGross(h), 0));

function today() { return new Date().toISOString().slice(0, 10); }
function fmt(v)  { return Number(v).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function formatAmount(v) { return fmt(v); }
function formatDate(d) { return new Date(d).toLocaleDateString('it-IT'); }
function initials(h) { return ((h.first_name?.[0] ?? '') + (h.last_name?.[0] ?? '')).toUpperCase(); }
function calcGross(h) {
  const effective = h.rate_type === 'daily' ? parseFloat(h.hourly_rate) / 8 : parseFloat(h.hourly_rate);
  return effective * parseFloat(h.hours);
}
function calcTax(h)   { const g = calcGross(h); return h.tax_inclusive ? g - g / 1.04 : g * 0.04; }
function clearFilters() { filterCollab.value = ''; filterProject.value = ''; filterMonth.value = ''; }

function resetForm() {
  Object.assign(form, { work_date: today(), hours: '', collaborator_id: '', project_id: '', tariff_id: '', description: '' });
  Object.assign(formErrors, { work_date: '', hours: '', collaborator_id: '', project_id: '', tariff_id: '' });
  saveError.value = '';
  tariffResolved.value = false;
}

function validate() {
  formErrors.work_date       = form.work_date       ? '' : 'Obbligatorio';
  formErrors.hours           = form.hours           ? '' : 'Obbligatorio';
  formErrors.collaborator_id = form.collaborator_id ? '' : 'Obbligatorio';
  formErrors.project_id      = form.project_id      ? '' : 'Obbligatorio';
  formErrors.tariff_id       = form.tariff_id       ? '' : 'Obbligatorio';
  return !Object.values(formErrors).some(Boolean);
}

async function resolveTariff() {
  if (!form.project_id) return;
  tariffResolved.value = false;
  try {
    const params = new URLSearchParams({ project_id: form.project_id });
    if (form.collaborator_id) params.set('collaborator_id', form.collaborator_id);
    const { data } = await api.get(`/projects/tariff/resolve?${params}`);
    form.tariff_id = data.id;
    tariffResolved.value = true;
  } catch {
    const def = tariffs.value.find(t => t.is_default);
    if (def) form.tariff_id = def.id;
  }
}

watch(() => modal.open, (v) => {
  // set default tariff only for brand-new entries, not when duplicating
  if (v && modal.isNew && !form.tariff_id) {
    const def = tariffs.value.find(t => t.is_default);
    if (def) form.tariff_id = def.id;
  }
});

async function load() {
  loading.value = true;
  try {
    const [h, c, p, t] = await Promise.all([
      api.get('/hours/collaborators'),
      api.get('/collaborators'),
      api.get('/projects'),
      api.get('/tariffs'),
    ]);
    hours.value         = h.data;
    collaborators.value = c.data.filter(x => x.is_active);
    projects.value      = p.data.filter(x => x.is_active);
    tariffs.value       = t.data;
  } finally { loading.value = false; }
}

function openNew() { resetForm(); modal.isNew = true; modal.open = true; modal._id = null; }

function openDuplicate(h) {
  resetForm();
  Object.assign(form, {
    work_date:       today(),
    hours:           h.hours,
    collaborator_id: h.collaborator_id,
    project_id:      h.project_id ?? '',
    tariff_id:       h.tariff_id,
    description:     h.description ?? '',
  });
  modal.isNew = true; modal.open = true; modal._id = null;
}

function openEdit(h) {
  resetForm();
  Object.assign(form, {
    work_date:       h.work_date.slice(0, 10),
    hours:           h.hours,
    collaborator_id: h.collaborator_id,
    project_id:      h.project_id ?? '',
    tariff_id:       h.tariff_id,
    description:     h.description ?? '',
  });
  modal.isNew = false; modal.open = true; modal._id = h.id;
}

function closeModal() { modal.open = false; }

async function save() {
  if (!validate()) return;
  saving.value = true; saveError.value = '';
  try {
    if (modal.isNew) await api.post('/hours/collaborators', form);
    else             await api.put(`/hours/collaborators/${modal._id}`, form);
    await load(); closeModal();
  } catch (err) {
    saveError.value = err.response?.data?.message ?? 'Errore durante il salvataggio.';
  } finally { saving.value = false; }
}

async function remove(h) {
  if (!confirm(`Eliminare le ${h.hours}h del ${formatDate(h.work_date)}?`)) return;
  await api.delete(`/hours/collaborators/${h.id}`);
  await load();
}

onMounted(load);
</script>

<style scoped>
.toolbar { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }
.select-input { padding: 0.5rem 0.75rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; background: #fff; outline: none; color: #374151; }
.select-input:focus { border-color: #0f3460; }
.btn-ghost { background: none; border: none; color: #6b7280; font-size: 0.875rem; cursor: pointer; padding: 0.5rem; border-radius: 8px; }
.btn-ghost:hover { background: #f3f4f6; }

.summary-bar { display: flex; gap: 1rem; flex-wrap: wrap; background: #fff; border-radius: 10px; padding: 0.875rem 1.25rem; margin-bottom: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.07); }
.summary-item { display: flex; flex-direction: column; }
.summary-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af; }
.summary-value { font-size: 1.1rem; font-weight: 700; color: #111827; font-family: 'Courier New', monospace; }
.summary-value.green { color: #059669; }

.data-table td { vertical-align: top; }

.fw { display: flex; align-items: center; gap: 0.5rem; }
.desc { max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #6b7280; font-size: 0.8rem; }

.avatar { width: 1.75rem; height: 1.75rem; border-radius: 50%; background: #0f3460; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; flex-shrink: 0; }

.tariff-name { display: inline; font-size: 0.8rem; color: #374151; }
.pill { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 9999px; font-size: 0.68rem; font-weight: 600; margin-top: 0.125rem; }
.pill.in { background: #d1fae5; color: #065f46; }
.pill.ex { background: #dbeafe; color: #1e40af; }
.pill.rate-pill { background: #f3f4f6; color: #374151; margin-top: 0.125rem; }
.rate-unit-small { font-size: 0.75rem; color: #9ca3af; }
.row-invoiced td { background: #fefce8 !important; }
.invoiced-icon { font-size: 1rem; cursor: default; opacity: 0.85; }

.modal { max-width: 500px; }

.preview-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem; }
.preview-title { font-size: 0.8rem; font-weight: 700; color: #374151; margin-bottom: 0.75rem; }
.preview-rows { display: flex; flex-direction: column; gap: 0.375rem; }
.preview-row { display: flex; justify-content: space-between; font-size: 0.875rem; color: #374151; }

.hint-ok { font-size: 0.78rem; color: #059669; font-weight: 600; }

/* ── Mobile card layout ─────────────────────────────────── */
@media (max-width: 640px) {
  .page { padding: 0.75rem; }
  .page-header { margin-bottom: 1rem; }
  .toolbar { gap: 0.5rem; }
  .select-input { flex: 1; min-width: 0; }

  .table-wrapper { overflow-x: unset; background: transparent; box-shadow: none; border-radius: 0; }
  .data-table, .data-table tbody { display: block; }
  .data-table thead { display: none; }

  .data-table tbody tr {
    display: block;
    background: #fff;
    border-radius: 10px;
    margin-bottom: 0.625rem;
    padding: 0.75rem 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.07);
  }
  .data-table tbody tr:hover td { background: transparent; }

  .data-table td {
    display: flex;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 0.25rem 0.5rem;
    padding: 0.3rem 0;
    border-bottom: none;
    font-size: 0.875rem;
  }
  .data-table td::before {
    content: attr(data-label);
    min-width: 4.5rem;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #9ca3af;
    flex-shrink: 0;
    padding-top: 0.15rem;
  }
  .data-table td.actions {
    display: flex;
    justify-content: flex-end;
    flex-wrap: nowrap;
    padding-top: 0.625rem;
    margin-top: 0.25rem;
    border-top: 1px solid #f3f4f6;
  }
  .data-table td.actions::before { display: none; }
  .desc { max-width: none; white-space: normal; }
}
</style>

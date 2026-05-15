<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>⏱️ Le mie ore</h2>
        <p class="page-sub">Ore lavorate per i clienti</p>
      </div>
      <button class="btn-primary" @click="openNew">+ Inserisci ore</button>
    </div>

    <!-- Filtri -->
    <div class="toolbar">
      <select v-model="filterClient" class="select-input">
        <option value="">Tutti i clienti</option>
        <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
      </select>
      <select v-model="filterProject" class="select-input">
        <option value="">Tutti i progetti</option>
        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <input v-model="filterMonth" type="month" class="select-input" />
      <button v-if="filterClient || filterProject || filterMonth" class="btn-ghost" @click="clearFilters">✕ Pulisci</button>
    </div>

    <!-- Riepilogo mese -->
    <div v-if="filtered.length" class="summary-bar">
      <div class="summary-item">
        <span class="summary-label">Ore totali</span>
        <span class="summary-value">{{ totalHours }}h</span>
      </div>
      <div class="summary-item">
        <span class="summary-label">Importo lordo</span>
        <span class="summary-value green">€ {{ formatAmount(totalGross) }}</span>
      </div>
      <div class="summary-item">
        <span class="summary-label">4% ritenuta</span>
        <span class="summary-value">€ {{ formatAmount(totalTax) }}</span>
      </div>
    </div>

    <!-- Skeleton -->
    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 5" :key="i" class="skeleton-row" />
    </div>

    <!-- Empty -->
    <div v-else-if="!filtered.length" class="empty-state">
      <span>⏱️</span>
      <p>Nessuna ora registrata.</p>
    </div>

    <!-- Tabella -->
    <div v-else class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>Data</th>
            <th>Cliente</th>
            <th>Tariffa</th>
            <th>Ore</th>
            <th>Tariffa</th>
            <th>Lordo</th>
            <th>4%</th>
            <th>Descrizione</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="h in filtered" :key="h.id">
            <td class="mono">{{ formatDate(h.work_date) }}</td>
            <td class="fw">{{ h.project_name || h.company_name }}</td>
            <td>
              <span class="tariff-name">{{ h.tariff_name }}</span>
              <span :class="['pill', h.tax_inclusive ? 'in' : 'ex']">
                {{ h.tax_inclusive ? '4% incl.' : '4% escl.' }}
              </span>
              <span class="pill rate-pill">{{ h.rate_type === 'daily' ? '📅 giornaliera' : '⏱️ oraria' }}</span>
            </td>
            <td class="mono">{{ h.hours }}h</td>
            <td class="mono">
              € {{ formatAmount(h.hourly_rate) }}
              <span class="rate-unit-small">{{ h.rate_type === 'daily' ? '/g' : '/h' }}</span>
            </td>
            <td class="mono green">€ {{ formatAmount(calcGross(h)) }}</td>
            <td class="mono muted">€ {{ formatAmount(calcTax(h)) }}</td>
            <td class="desc">{{ h.description || '—' }}</td>
            <td class="actions">
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
            <h3>{{ modal.isNew ? 'Inserisci ore' : 'Modifica ore' }}</h3>
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

            <div class="field" :class="{ error: formErrors.client_id }">
              <label>Cliente *</label>
              <select v-model="form.client_id" @change="onClientChange">
                <option value="">Seleziona cliente…</option>
                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
              </select>
              <span v-if="formErrors.client_id" class="field-error">{{ formErrors.client_id }}</span>
            </div>

            <div class="field" :class="{ error: formErrors.project_id }">
              <label>Progetto *</label>
              <select v-model="form.project_id" :disabled="!form.client_id" @change="resolveTariff">
                <option value="">Seleziona progetto…</option>
                <option v-for="p in filteredProjects" :key="p.id" :value="p.id">{{ p.name }}</option>
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

            <!-- Preview se tariffa selezionata -->
            <div v-if="selectedTariff" class="preview-box">
              <div class="preview-title">🔢 Anteprima per {{ form.hours || 0 }} ore</div>
              <div class="preview-rows">
                <div class="preview-row"><span>Lordo</span><span class="mono">€ {{ modalPreview.gross }}</span></div>
                <div class="preview-row"><span>4% {{ selectedTariff.tax_inclusive ? '(scorporato)' : '(aggiunto)' }}</span><span class="mono">€ {{ modalPreview.tax }}</span></div>
                <div class="preview-row total"><span>Totale netto</span><span class="mono">€ {{ modalPreview.net }}</span></div>
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

const hours      = ref([]);
const clients    = ref([]);
const projects   = ref([]);
const tariffs    = ref([]);
const loading    = ref(true);
const saving     = ref(false);
const saveError  = ref('');
const filterClient  = ref('');
const filterProject = ref('');
const filterMonth   = ref(new Date().toISOString().slice(0, 7));
const tariffResolved = ref(false);

const modal = reactive({ open: false, isNew: true, _id: null });
const form  = reactive({ work_date: today(), hours: '', client_id: '', project_id: '', tariff_id: '', description: '' });
const formErrors = reactive({ work_date: '', hours: '', client_id: '', project_id: '', tariff_id: '' });

// ── Computed ─────────────────────────────────────────────
const filteredProjects = computed(() =>
  projects.value.filter(p => !form.client_id || p.client_id == form.client_id)
);

const filtered = computed(() => {
  return hours.value.filter(h => {
    if (filterClient.value  && h.client_id  != filterClient.value)  return false;
    if (filterProject.value && h.project_id != filterProject.value) return false;
    if (filterMonth.value   && !h.work_date.slice(0, 7).startsWith(filterMonth.value)) return false;
    return true;
  });
});

const selectedTariff = computed(() =>
  tariffs.value.find(t => t.id == form.tariff_id) ?? null
);

const modalPreview = computed(() => {
  const t = selectedTariff.value;
  if (!t) return {};
  const effective = t.rate_type === 'daily' ? parseFloat(t.hourly_rate) / 8 : parseFloat(t.hourly_rate);
  const gross = effective * (parseFloat(form.hours) || 0);
  const tax   = t.tax_inclusive ? gross - gross / 1.04 : gross * 0.04;
  const net   = t.tax_inclusive ? gross / 1.04 : gross + tax;
  return { gross: fmt(gross), tax: fmt(tax), net: fmt(net) };
});

const totalHours = computed(() => filtered.value.reduce((s, h) => s + parseFloat(h.hours), 0));
const totalGross = computed(() => filtered.value.reduce((s, h) => s + calcGross(h), 0));
const totalTax   = computed(() => filtered.value.reduce((s, h) => s + calcTax(h), 0));

// ── Helpers ──────────────────────────────────────────────
function today() { return new Date().toISOString().slice(0, 10); }
function fmt(v)  { return Number(v).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function formatAmount(v) { return fmt(v); }
function formatDate(d) { return new Date(d).toLocaleDateString('it-IT'); }

function calcGross(h) {
  const effective = h.rate_type === 'daily' ? parseFloat(h.hourly_rate) / 8 : parseFloat(h.hourly_rate);
  return effective * parseFloat(h.hours);
}
function calcTax(h) {
  const g = calcGross(h);
  return h.tax_inclusive ? g - g / 1.04 : g * 0.04;
}

function clearFilters() { filterClient.value = ''; filterProject.value = ''; filterMonth.value = ''; }

function resetForm() {
  Object.assign(form, { work_date: today(), hours: '', client_id: '', project_id: '', tariff_id: '', description: '' });
  Object.assign(formErrors, { work_date: '', hours: '', client_id: '', project_id: '', tariff_id: '' });
  saveError.value = '';
  tariffResolved.value = false;
}

function validate() {
  formErrors.work_date  = form.work_date  ? '' : 'Obbligatorio';
  formErrors.hours      = form.hours      ? '' : 'Obbligatorio';
  formErrors.client_id  = form.client_id  ? '' : 'Obbligatorio';
  formErrors.project_id = form.project_id ? '' : 'Obbligatorio';
  formErrors.tariff_id  = form.tariff_id  ? '' : 'Obbligatorio';
  return !Object.values(formErrors).some(Boolean);
}

function onClientChange() {
  form.project_id  = '';
  form.tariff_id   = '';
  tariffResolved.value = false;
}

async function resolveTariff() {
  if (!form.project_id) return;
  tariffResolved.value = false;
  try {
    const { data } = await api.get(`/projects/tariff/resolve?project_id=${form.project_id}`);
    form.tariff_id = data.id;
    tariffResolved.value = true;
  } catch {
    // fallback: usa tariffa di default se disponibile
    const def = tariffs.value.find(t => t.is_default);
    if (def) form.tariff_id = def.id;
  }
}

// Auto-seleziona tariffa di default alla prima apertura modale
watch(() => modal.open, (v) => {
  if (v && modal.isNew && !form.project_id) {
    const def = tariffs.value.find(t => t.is_default);
    if (def) form.tariff_id = def.id;
  }
});

// ── Fetch ────────────────────────────────────────────────
async function load() {
  loading.value = true;
  try {
    const [h, c, p, t] = await Promise.all([
      api.get('/hours/my'),
      api.get('/clients'),
      api.get('/projects'),
      api.get('/tariffs'),
    ]);
    hours.value    = h.data;
    clients.value  = c.data.filter(x => x.is_active);
    projects.value = p.data.filter(x => x.is_active);
    tariffs.value  = t.data;
  } finally {
    loading.value = false;
  }
}

function openNew() { resetForm(); modal.isNew = true; modal.open = true; modal._id = null; }

function openEdit(h) {
  resetForm();
  Object.assign(form, {
    work_date:   h.work_date.slice(0, 10),
    hours:       h.hours,
    client_id:   h.client_id,
    project_id:  h.project_id ?? '',
    tariff_id:   h.tariff_id,
    description: h.description ?? '',
  });
  modal.isNew = false; modal.open = true; modal._id = h.id;
}

function closeModal() { modal.open = false; }

async function save() {
  if (!validate()) return;
  saving.value = true; saveError.value = '';
  try {
    if (modal.isNew) await api.post('/hours/my', form);
    else             await api.put(`/hours/my/${modal._id}`, form);
    await load(); closeModal();
  } catch (err) {
    saveError.value = err.response?.data?.message ?? 'Errore durante il salvataggio.';
  } finally { saving.value = false; }
}

async function remove(h) {
  if (!confirm(`Eliminare le ${h.hours}h del ${formatDate(h.work_date)}?`)) return;
  await api.delete(`/hours/my/${h.id}`);
  await load();
}

onMounted(load);
</script>

<style scoped>
.page { padding: 2rem; max-width: 1200px; }

.page-header {
  display: flex; justify-content: space-between;
  align-items: flex-start; margin-bottom: 1.5rem;
  gap: 1rem; flex-wrap: wrap;
}
.page-header h2 { font-size: 1.5rem; font-weight: 700; color: #111827; }
.page-sub       { font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem; }

.toolbar {
  display: flex; align-items: center; gap: 0.75rem;
  margin-bottom: 1rem; flex-wrap: wrap;
}
.select-input {
  padding: 0.5rem 0.75rem; border: 1.5px solid #d1d5db;
  border-radius: 8px; font-size: 0.875rem; background: #fff;
  outline: none; color: #374151;
}
.select-input:focus { border-color: #0f3460; }

.btn-ghost {
  background: none; border: none; color: #6b7280;
  font-size: 0.875rem; cursor: pointer; padding: 0.5rem;
  border-radius: 8px;
}
.btn-ghost:hover { background: #f3f4f6; }

/* ── Summary bar ───────────────────────────────────────── */
.summary-bar {
  display: flex; gap: 1rem; flex-wrap: wrap;
  background: #fff; border-radius: 10px;
  padding: 0.875rem 1.25rem; margin-bottom: 1rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.07);
}
.summary-item { display: flex; flex-direction: column; }
.summary-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af; }
.summary-value { font-size: 1.1rem; font-weight: 700; color: #111827; font-family: 'Courier New', monospace; }
.summary-value.green { color: #059669; }

/* ── Table ─────────────────────────────────────────────── */
.table-wrapper { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th {
  text-align: left; padding: 0.75rem 0.75rem;
  font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.05em; color: #6b7280; border-bottom: 1px solid #e5e7eb; background: #f9fafb;
}
.data-table td { padding: 0.65rem 0.75rem; border-bottom: 1px solid #f3f4f6; color: #374151; vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover td { background: #f9fafb; }

.mono  { font-family: 'Courier New', monospace; }
.fw    { font-weight: 600; color: #111827; }
.green { color: #059669; font-weight: 600; }
.muted { color: #9ca3af; }
.desc  { max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #6b7280; font-size: 0.8rem; }

.tariff-name { display: block; font-size: 0.8rem; color: #374151; }
.pill {
  display: inline-block; padding: 0.1rem 0.4rem;
  border-radius: 9999px; font-size: 0.68rem; font-weight: 600; margin-top: 0.125rem;
}
.pill.in { background: #d1fae5; color: #065f46; }
.pill.ex { background: #dbeafe; color: #1e40af; }
.pill.rate-pill { background: #f3f4f6; color: #374151; margin-top: 0.125rem; }
.rate-unit-small { font-size: 0.75rem; color: #9ca3af; }

.actions { display: flex; gap: 0.25rem; }
.btn-icon { background: none; border: none; cursor: pointer; padding: 0.25rem 0.375rem; border-radius: 6px; font-size: 1rem; transition: background 0.15s; }
.btn-icon:hover { background: #f3f4f6; }

/* ── Modal ─────────────────────────────────────────────── */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 500; padding: 1rem; }
.modal { background: #fff; border-radius: 16px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid #e5e7eb; }
.modal-header h3 { font-size: 1.1rem; font-weight: 700; color: #111827; }
.modal-close { background: none; border: none; font-size: 1.1rem; cursor: pointer; color: #6b7280; padding: 0.25rem; border-radius: 4px; }
.modal-close:hover { background: #f3f4f6; color: #111827; }
.modal-form { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 480px) { .form-row { grid-template-columns: 1fr; } }
.field { display: flex; flex-direction: column; gap: 0.375rem; }
.field label { font-size: 0.8rem; font-weight: 600; color: #374151; }
.field input, .field select, .field textarea {
  padding: 0.5rem 0.75rem; border: 1.5px solid #d1d5db; border-radius: 8px;
  font-size: 0.9rem; color: #111827; background: #f9fafb; outline: none;
  transition: border-color 0.2s; font-family: inherit; resize: vertical;
}
.field input:focus, .field select:focus, .field textarea:focus { border-color: #0f3460; background: #fff; box-shadow: 0 0 0 3px rgba(15,52,96,0.1); }
.field.error input, .field.error select { border-color: #ef4444; }
.field-error { font-size: 0.78rem; color: #ef4444; }

.preview-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem; }
.preview-title { font-size: 0.8rem; font-weight: 700; color: #374151; margin-bottom: 0.75rem; }
.preview-rows { display: flex; flex-direction: column; gap: 0.375rem; }
.preview-row { display: flex; justify-content: space-between; font-size: 0.875rem; color: #374151; }
.preview-row.total { border-top: 1px solid #e2e8f0; padding-top: 0.375rem; font-weight: 700; color: #059669; }

.alert-error { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #b91c1c; padding: 0.75rem; font-size: 0.875rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 0.5rem; }

.btn-primary { display: inline-flex; align-items: center; gap: 0.375rem; background: linear-gradient(135deg, #0f3460, #1a6fb5); color: #fff; border: none; border-radius: 8px; padding: 0.6rem 1.1rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: opacity 0.2s; white-space: nowrap; }
.btn-primary:hover:not(:disabled) { opacity: 0.9; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { background: #f3f4f6; color: #374151; border: none; border-radius: 8px; padding: 0.6rem 1.1rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: background 0.15s; }
.btn-secondary:hover { background: #e5e7eb; }

.hint-ok { font-size: 0.78rem; color: #059669; font-weight: 600; }
.spinner { display: inline-block; width: 0.875rem; height: 0.875rem; border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.skeleton-list { display: flex; flex-direction: column; gap: 0.5rem; }
.skeleton-row { height: 3rem; border-radius: 8px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.2s infinite; }
@keyframes shimmer { to { background-position: -200% 0; } }

.empty-state { text-align: center; padding: 4rem 2rem; color: #9ca3af; }
.empty-state span { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; }

@media (max-width: 768px) { .page { padding: 1rem; } }
</style>

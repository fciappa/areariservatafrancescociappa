<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>⏱️ Le mie ore</h2>
        <p class="page-sub">Inserisci le ore lavorate — l'admin le approverà prima della fatturazione</p>
      </div>
      <div class="header-actions">
        <button class="btn-secondary" @click="openMulti">📅 Più giorni</button>
        <button class="btn-primary" @click="openNew">+ Inserisci ore</button>
      </div>
    </div>

    <!-- Filtro mese -->
    <div class="toolbar">
      <input v-model="filterMonth" type="month" class="select-input" />
      <button v-if="filterMonth" class="btn-ghost" @click="filterMonth = ''">✕ Pulisci</button>
    </div>

    <!-- Skeleton -->
    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 5" :key="i" class="skeleton-row" />
    </div>

    <!-- Empty -->
    <div v-else-if="!filtered.length" class="empty-state">
      <span>⏱️</span>
      <p>Nessuna ora registrata{{ filterMonth ? ' per questo mese' : '' }}.</p>
    </div>

    <!-- Tabella -->
    <div v-else class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>Data</th>
            <th>Ore</th>
            <th>Progetto / Tariffa</th>
            <th>€/ora</th>
            <th>Lordo</th>
            <th>Stato</th>
            <th>Note</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="h in filtered" :key="h.id" :class="rowClass(h)">
            <td class="mono" data-label="Data">{{ formatDate(h.work_date) }}</td>
            <td class="mono" data-label="Ore">{{ h.hours }}h</td>
            <td data-label="Progetto">
              <span class="tariff-name">{{ h.tariff_name }}</span>
              <span :class="['pill', h.tax_inclusive ? 'in' : 'ex']">
                {{ h.tax_inclusive ? '4% incl.' : '4% escl.' }}
              </span>
            </td>
            <td class="mono" data-label="€/ora">
              € {{ fmt(h.hourly_rate) }}
              <span class="rate-unit">{{ h.rate_type === 'daily' ? '/g' : '/h' }}</span>
            </td>
            <td class="mono green" data-label="Lordo">€ {{ fmt(calcGross(h)) }}</td>
            <td data-label="Stato">
              <span v-if="h.invoiced_at" class="status-pill invoiced">🧾 Fatturata</span>
              <span v-else-if="h.status === 'approved'" class="status-pill approved">✅ Approvata</span>
              <span v-else-if="h.status === 'rejected'" class="status-pill rejected">❌ Rifiutata</span>
              <span v-else class="status-pill pending">⏳ In attesa</span>
            </td>
            <td class="desc" data-label="Note">{{ h.description || '—' }}</td>
            <td class="actions" data-label="Azioni">
              <button
                v-if="!h.invoiced_at && h.status === 'pending'"
                class="btn-icon"
                title="Elimina"
                @click="remove(h)"
              >🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modale inserimento -->
    <Teleport to="body">
      <div v-if="modal.open" class="modal-overlay" @click.self="modal.open = false">
        <div class="modal">
          <div class="modal-header">
            <h3>{{ modal.mode === 'multi' ? 'Inserisci più giorni' : 'Inserisci ore' }}</h3>
            <button class="modal-close" @click="modal.open = false">✕</button>
          </div>
          <form class="modal-form" @submit.prevent="save">

            <div class="mode-tabs">
              <button type="button" :class="['mode-tab', modal.mode === 'single' && 'active']" @click="modal.mode = 'single'">Singolo giorno</button>
              <button type="button" :class="['mode-tab', modal.mode === 'multi' && 'active']" @click="modal.mode = 'multi'">Più giorni</button>
            </div>

            <template v-if="modal.mode === 'multi'">
              <div class="form-row">
                <div class="field">
                  <label>Dal *</label>
                  <input v-model="multiForm.date_from" type="date" :max="today" />
                </div>
                <div class="field">
                  <label>Al *</label>
                  <input v-model="multiForm.date_to" type="date" :max="today" />
                </div>
              </div>

              <div class="field">
                <label>Giorni della settimana</label>
                <div class="weekday-grid">
                  <label v-for="w in WEEKDAYS" :key="w.day" :class="['weekday-btn', selectedWeekdays.includes(w.day) && 'on']">
                    <input type="checkbox" :value="w.day" v-model="selectedWeekdays" hidden />
                    {{ w.label }}
                  </label>
                </div>
                <span class="hint-ok" v-if="selectedDates.length">
                  {{ selectedDates.length }} {{ selectedDates.length === 1 ? 'giorno' : 'giorni' }} selezionati
                </span>
                <span class="field-error" v-else-if="multiForm.date_from && multiForm.date_to">Nessun giorno nel range</span>
              </div>
            </template>

            <div v-if="modal.mode === 'single'" class="form-row">
              <div class="field" :class="{ error: err.work_date }">
                <label>Data *</label>
                <input v-model="form.work_date" type="date" :max="today" />
                <span v-if="err.work_date" class="field-error">{{ err.work_date }}</span>
              </div>
              <div class="field" :class="{ error: err.hours }">
                <label>Ore *</label>
                <input v-model="form.hours" type="number" min="0.25" max="24" step="0.25" placeholder="8" />
                <span v-if="err.hours" class="field-error">{{ err.hours }}</span>
              </div>
            </div>

            <div v-else class="field" :class="{ error: err.hours }">
              <label>Ore per giorno *</label>
              <input v-model="form.hours" type="number" min="0.25" max="24" step="0.25" placeholder="8" />
              <span v-if="err.hours" class="field-error">{{ err.hours }}</span>
            </div>

            <div class="field" :class="{ error: err.project_id }">
              <label>Progetto *</label>
              <select v-model="form.project_id" @change="onProjectChange">
                <option value="">Seleziona progetto…</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
              <span v-if="err.project_id" class="field-error">{{ err.project_id }}</span>
            </div>

            <div v-if="selectedProject" class="field">
              <label>Tariffa</label>
              <div class="tariff-info">
                {{ selectedProject.tariff_name }} —
                € {{ fmt(selectedProject.hourly_rate) }}{{ selectedProject.rate_type === 'daily' ? '/giorno' : '/h' }}
              </div>
            </div>

            <!-- Preview -->
            <div v-if="selectedProject && form.hours" class="preview-box">
              <div class="preview-title">🔢 Anteprima per {{ form.hours }} ore</div>
              <div class="preview-rows">
                <div class="preview-row"><span>Lordo da ricevere</span><span class="mono">€ {{ preview.gross }}</span></div>
                <div class="preview-row"><span>4% ({{ selectedProject.tax_inclusive ? 'scorporato' : 'aggiunto' }})</span><span class="mono">€ {{ preview.tax }}</span></div>
              </div>
            </div>

            <div class="field">
              <label>Descrizione</label>
              <textarea v-model="form.description" rows="2" placeholder="Attività svolta…" />
            </div>

            <div v-if="saveError" class="alert-error">{{ saveError }}</div>

            <div class="modal-footer">
              <button type="button" class="btn-secondary" @click="modal.open = false">Annulla</button>
              <button type="submit" class="btn-primary" :disabled="saving || (modal.mode === 'multi' && !selectedDates.length)">
                <span v-if="saving" class="spinner" />
                {{ saving ? 'Invio…' : modal.mode === 'multi' ? `Invia ${selectedDates.length} giorni` : 'Invia per approvazione' }}
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

const hours       = ref([]);
const projects    = ref([]);
const loading     = ref(true);
const saving      = ref(false);
const saveError   = ref('');
const filterMonth = ref('');
const today       = new Date().toISOString().slice(0, 10);

const modal = reactive({ open: false, mode: 'single' });
const form  = reactive({ work_date: today, hours: '', project_id: '', description: '' });
const err   = reactive({ work_date: '', hours: '', project_id: '' });
const multiForm        = reactive({ date_from: today, date_to: today });
const selectedWeekdays = ref([1, 2, 3, 4, 5]);

const WEEKDAYS = [
  { day: 1, label: 'Lun' }, { day: 2, label: 'Mar' }, { day: 3, label: 'Mer' },
  { day: 4, label: 'Gio' }, { day: 5, label: 'Ven' }, { day: 6, label: 'Sab' }, { day: 0, label: 'Dom' },
];

const filtered = computed(() =>
  hours.value.filter(h =>
    !filterMonth.value || h.work_date.slice(0, 7) === filterMonth.value
  )
);

const selectedProject = computed(() =>
  projects.value.find(p => p.id == form.project_id) ?? null
);

const preview = computed(() => {
  const p = selectedProject.value;
  if (!p || !form.hours) return {};
  const rate  = p.rate_type === 'daily' ? parseFloat(p.hourly_rate) / 8 : parseFloat(p.hourly_rate);
  const gross = rate * parseFloat(form.hours);
  const tax   = p.tax_inclusive ? gross - gross / 1.04 : gross * 0.04;
  return { gross: fmt(gross), tax: fmt(tax) };
});

const selectedDates = computed(() => {
  if (!multiForm.date_from || !multiForm.date_to) return [];
  const start = new Date(multiForm.date_from + 'T00:00:00');
  const end   = new Date(multiForm.date_to + 'T00:00:00');
  const max   = new Date(today + 'T00:00:00');
  if (start > end || start > max) return [];
  const dates = [];
  const hardEnd = end > max ? max : end;
  for (let d = new Date(start); d <= hardEnd; d.setDate(d.getDate() + 1)) {
    if (selectedWeekdays.value.includes(d.getDay())) {
      dates.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`);
    }
  }
  return dates;
});

function fmt(v) { return Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function formatDate(d) { return new Date(d).toLocaleDateString('it-IT'); }
function calcGross(h) {
  const rate = h.rate_type === 'daily' ? parseFloat(h.hourly_rate) / 8 : parseFloat(h.hourly_rate);
  return rate * parseFloat(h.hours);
}
function rowClass(h) {
  if (h.invoiced_at)        return 'row-invoiced';
  if (h.status === 'pending')  return 'row-pending';
  if (h.status === 'rejected') return 'row-rejected';
  return '';
}

function onProjectChange() {}

function validate() {
  err.work_date  = modal.mode === 'single' && !form.work_date ? 'Obbligatorio' : '';
  err.hours      = form.hours      ? '' : 'Obbligatorio';
  err.project_id = form.project_id ? '' : 'Obbligatorio';
  return !Object.values(err).some(Boolean);
}

function openNew() {
  Object.assign(form, { work_date: today, hours: '', project_id: '', description: '' });
  Object.assign(err,  { work_date: '', hours: '', project_id: '' });
  Object.assign(multiForm, { date_from: today, date_to: today });
  selectedWeekdays.value = [1, 2, 3, 4, 5];
  saveError.value = '';
  modal.mode = 'single';
  modal.open = true;
}

function openMulti() {
  Object.assign(form, { work_date: today, hours: '', project_id: '', description: '' });
  Object.assign(err,  { work_date: '', hours: '', project_id: '' });
  Object.assign(multiForm, { date_from: today, date_to: today });
  selectedWeekdays.value = [1, 2, 3, 4, 5];
  saveError.value = '';
  modal.mode = 'multi';
  modal.open = true;
}

async function save() {
  if (!validate()) return;
  const p = selectedProject.value;
  saving.value = true; saveError.value = '';
  try {
    if (modal.mode === 'multi') {
      if (!selectedDates.value.length) {
        saveError.value = 'Nessun giorno selezionato nel range.';
        return;
      }
      await api.post('/hours/collaborators/bulk', {
        rows: selectedDates.value.map(date => ({
          project_id:  form.project_id,
          tariff_id:   p?.tariff_id,
          work_date:   date,
          hours:       form.hours,
          description: form.description,
        })),
      });
    } else {
      await api.post('/hours/collaborators', {
        project_id:  form.project_id,
        tariff_id:   p?.tariff_id,
        work_date:   form.work_date,
        hours:       form.hours,
        description: form.description,
      });
    }
    await load();
    modal.open = false;
  } catch (e) {
    saveError.value = e.response?.data?.message ?? 'Errore durante il salvataggio.';
  } finally {
    saving.value = false;
  }
}

async function remove(h) {
  if (!confirm(`Eliminare le ${h.hours}h del ${formatDate(h.work_date)}?`)) return;
  await api.delete(`/hours/collaborators/${h.id}`);
  hours.value = hours.value.filter(x => x.id !== h.id);
}

async function load() {
  loading.value = true;
  try {
    const [h, p] = await Promise.all([
      api.get('/hours/collaborators'),
      api.get('/projects/assigned'),
    ]);
    hours.value    = h.data;
    projects.value = p.data;
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.toolbar { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }
.select-input { padding: 0.5rem 0.75rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; background: #fff; outline: none; color: #374151; }
.select-input:focus { border-color: #0f3460; }
.btn-ghost { background: none; border: none; color: #6b7280; font-size: 0.875rem; cursor: pointer; padding: 0.5rem; border-radius: 8px; }
.btn-ghost:hover { background: #f3f4f6; }

.tariff-name { font-size: 0.8rem; color: #374151; }
.pill { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 9999px; font-size: 0.68rem; font-weight: 600; margin-left: 0.25rem; }
.pill.in { background: #d1fae5; color: #065f46; }
.pill.ex { background: #dbeafe; color: #1e40af; }
.rate-unit { font-size: 0.75rem; color: #9ca3af; }
.desc { max-width: 160px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #6b7280; font-size: 0.8rem; }

/* Status pills */
.status-pill { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.72rem; font-weight: 600; white-space: nowrap; }
.status-pill.pending  { background: #fed7aa; color: #9a3412; }
.status-pill.approved { background: #d1fae5; color: #065f46; }
.status-pill.rejected { background: #fee2e2; color: #991b1b; }
.status-pill.invoiced { background: #fef9c3; color: #854d0e; }

/* Row colors */
.row-invoiced td { background: #fefce8 !important; }
.row-pending td  { background: #fff7ed !important; }
.row-rejected td { background: #fef2f2 !important; }

/* Modal */
.modal { max-width: 480px; }
.tariff-info { font-size: 0.875rem; color: #374151; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.5rem 0.75rem; }
.preview-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem; }
.preview-title { font-size: 0.8rem; font-weight: 700; color: #374151; margin-bottom: 0.75rem; }
.preview-rows { display: flex; flex-direction: column; gap: 0.375rem; }
.preview-row { display: flex; justify-content: space-between; font-size: 0.875rem; color: #374151; }

.mode-tabs { display: flex; gap: 0.5rem; margin-bottom: 0.25rem; }
.mode-tab {
  border: 1px solid #d1d5db; background: #fff; color: #374151;
  border-radius: 9999px; padding: 0.35rem 0.75rem; font-size: 0.78rem;
  font-weight: 600; cursor: pointer;
}
.mode-tab.active { background: #0f3460; border-color: #0f3460; color: #fff; }
.weekday-grid { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.25rem; }
.weekday-btn {
  border: 1px solid #d1d5db; border-radius: 9999px;
  padding: 0.2rem 0.55rem; font-size: 0.75rem; color: #374151; cursor: pointer;
  background: #fff;
}
.weekday-btn.on { background: #eff6ff; border-color: #60a5fa; color: #1d4ed8; }
.hint-ok { font-size: 0.76rem; color: #059669; margin-top: 0.35rem; display: inline-block; }

/* Mobile cards */
@media (max-width: 640px) {
  .page { padding: 0.75rem; }
  .table-wrapper { overflow-x: unset; background: transparent; box-shadow: none; border-radius: 0; }
  .data-table, .data-table tbody { display: block; }
  .data-table thead { display: none; }
  .data-table tbody tr {
    display: block; background: #fff; border-radius: 10px;
    margin-bottom: 0.625rem; padding: 0.75rem 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.07);
  }
  .data-table td {
    display: flex; align-items: flex-start; flex-wrap: wrap;
    gap: 0.25rem 0.5rem; padding: 0.3rem 0;
    border-bottom: none; font-size: 0.875rem;
  }
  .data-table td::before {
    content: attr(data-label); min-width: 4.5rem;
    font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.04em; color: #9ca3af; flex-shrink: 0; padding-top: 0.15rem;
  }
  .data-table td.actions { display: flex; justify-content: flex-end; padding-top: 0.625rem; border-top: 1px solid #f3f4f6; }
  .data-table td.actions::before { display: none; }
  .desc { max-width: none; white-space: normal; }
}
</style>

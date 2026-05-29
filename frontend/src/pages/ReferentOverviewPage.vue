<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>📉 Controllo Uscite</h2>
        <p class="page-sub">Ore collaboratori e costi dei progetti assegnati</p>
      </div>
      <input v-model="month" type="month" class="select-input" />
    </div>

    <div class="toolbar">
      <select v-model="selectedProject" class="select-input">
        <option value="">Tutti i progetti assegnati</option>
        <option v-for="p in summary" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <button v-if="selectedProject" class="btn-ghost" @click="selectedProject = ''">✕ Pulisci</button>
    </div>

    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 4" :key="i" class="skeleton-row" />
    </div>

    <template v-else>
      <div class="summary-bar" v-if="summary.length">
        <div class="summary-item">
          <span class="summary-label">Ore fatturate</span>
          <span class="summary-value">{{ fmt(totalInvoicedHours) }}h</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">Ore da fatturare</span>
          <span class="summary-value">{{ fmt(totalToInvoiceHours) }}h</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">€ fatturate</span>
          <span class="summary-value green">€ {{ fmt(totalInvoicedGross) }}</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">€ da fatturare</span>
          <span class="summary-value orange">€ {{ fmt(totalToInvoiceGross) }}</span>
        </div>
      </div>

      <section class="card" v-if="summary.length">
        <h3 class="card-title-sm">📁 Totali per progetto</h3>
        <table class="mini-table">
          <thead>
            <tr><th>Progetto</th><th>Cliente</th><th>Ore fatturate</th><th>Ore da fatturare</th><th>€ fatturate</th><th>€ da fatturare</th></tr>
          </thead>
          <tbody>
            <tr v-for="p in summary" :key="p.id">
              <td class="fw">{{ p.name }}</td>
              <td>{{ p.company_name }}</td>
              <td class="mono">{{ fmt(p.invoiced_hours) }}h</td>
              <td class="mono">{{ fmt(p.to_invoice_hours) }}h</td>
              <td class="mono green">€ {{ fmt(p.invoiced_gross) }}</td>
              <td class="mono orange">€ {{ fmt(p.to_invoice_gross) }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="card">
        <h3 class="card-title-sm">🗓️ Dettaglio ore collaboratori</h3>
        <div v-if="!hours.length" class="empty-small">Nessuna ora trovata per il mese selezionato.</div>
        <table v-else class="mini-table">
          <thead>
            <tr><th>Data</th><th>Progetto</th><th>Collaboratore</th><th>Ore</th><th>Stato</th><th>Fatturazione</th><th>€ lordo</th></tr>
          </thead>
          <tbody>
            <tr v-for="h in hours" :key="h.id" :class="{ 'row-invoiced': h.invoiced_at }">
              <td class="mono">{{ formatDate(h.work_date) }}</td>
              <td>{{ h.project_name || '—' }}</td>
              <td>{{ h.first_name }} {{ h.last_name }}</td>
              <td class="mono">{{ h.hours }}h</td>
              <td>
                <span :class="['badge', h.status]">{{ statusLabel(h.status) }}</span>
              </td>
              <td>
                <span :class="['badge', h.invoiced_at ? 'issued' : 'draft']">{{ h.invoiced_at ? 'Fatturata' : 'Da fatturare' }}</span>
              </td>
              <td class="mono green">€ {{ fmt(calcGross(h)) }}</td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import api from '../services/api.js';

const month = ref(new Date().toISOString().slice(0, 7));
const selectedProject = ref('');
const loading = ref(true);
const summary = ref([]);
const hours = ref([]);

const totalInvoicedHours = computed(() =>
  summary.value.reduce((s, p) => s + parseFloat(p.invoiced_hours || 0), 0)
);
const totalToInvoiceHours = computed(() =>
  summary.value.reduce((s, p) => s + parseFloat(p.to_invoice_hours || 0), 0)
);
const totalInvoicedGross = computed(() =>
  summary.value.reduce((s, p) => s + parseFloat(p.invoiced_gross || 0), 0)
);
const totalToInvoiceGross = computed(() =>
  summary.value.reduce((s, p) => s + parseFloat(p.to_invoice_gross || 0), 0)
);

function fmt(v) {
  return Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('it-IT');
}

function statusLabel(s) {
  return { pending: 'In attesa', approved: 'Approvata', rejected: 'Rifiutata' }[s] ?? s;
}

function calcGross(h) {
  const rate = h.rate_type === 'daily' ? parseFloat(h.hourly_rate) / 8 : parseFloat(h.hourly_rate);
  return rate * parseFloat(h.hours);
}

async function load() {
  loading.value = true;
  try {
    const params = new URLSearchParams({ month: month.value });
    const [s, h] = await Promise.all([
      api.get(`/referent/projects/summary?${params}`),
      api.get(`/referent/projects/hours?${params}${selectedProject.value ? `&project_id=${selectedProject.value}` : ''}`),
    ]);
    summary.value = s.data;
    hours.value = h.data;
  } finally {
    loading.value = false;
  }
}

watch([month, selectedProject], load);
onMounted(load);
</script>

<style scoped>
.toolbar { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }
.select-input { padding: 0.5rem 0.75rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; background: #fff; outline: none; color: #374151; }
.select-input:focus { border-color: #0f3460; }
.btn-ghost { background: none; border: none; color: #6b7280; font-size: 0.875rem; cursor: pointer; padding: 0.5rem; border-radius: 8px; }
.btn-ghost:hover { background: #f3f4f6; }

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
.summary-value.orange { color: #d97706; }

.card-title-sm { font-size: 0.95rem; font-weight: 700; color: #111827; margin-bottom: 1rem; }
.mini-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.mini-table th { text-align: left; padding: 0.375rem 0.5rem; font-size: 0.72rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #e5e7eb; }
.mini-table td { padding: 0.5rem; border-bottom: 1px solid #f3f4f6; color: #374151; }
.badge.pending { background: #fed7aa; color: #9a3412; }
.badge.approved { background: #d1fae5; color: #065f46; }
.badge.rejected { background: #fee2e2; color: #991b1b; }
.badge.issued { background: #dbeafe; color: #1d4ed8; }
.badge.draft { background: #f3f4f6; color: #6b7280; }
.row-invoiced td { background: #fefce8 !important; }
.empty-small { text-align: center; padding: 1.5rem; color: #9ca3af; font-size: 0.875rem; }
</style>

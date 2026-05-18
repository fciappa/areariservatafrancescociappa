<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>🧾 Fatture</h2>
        <p class="page-sub">Storico e gestione fatture simulate</p>
      </div>
      <RouterLink to="/invoices/new" class="btn-primary">+ Nuova fattura</RouterLink>
    </div>

    <!-- Filtri -->
    <div class="toolbar">
      <input v-model="filterYear"  type="number" class="select-input" placeholder="Anno" min="2020" :max="currentYear" style="width:90px" />
      <select v-model="filterMonth" class="select-input">
        <option value="">Tutti i mesi</option>
        <option v-for="(m, i) in months" :key="i+1" :value="i+1">{{ m }}</option>
      </select>
      <select v-model="filterStatus" class="select-input">
        <option value="">Tutti gli stati</option>
        <option value="draft">Bozza</option>
        <option value="issued">Emessa</option>
        <option value="paid">Pagata</option>
      </select>
      <button class="btn-ghost" @click="load">🔄 Aggiorna</button>
    </div>

    <!-- Riepilogo mese -->
    <div v-if="invoices.length" class="summary-bar">
      <div class="summary-item">
        <span class="summary-label">N° fatture</span>
        <span class="summary-value">{{ filtered.length }}</span>
      </div>
      <div class="summary-item">
        <span class="summary-label">Totale fatturato</span>
        <span class="summary-value green">€ {{ formatAmount(totalInvoiced) }}</span>
      </div>
      <div class="summary-item">
        <span class="summary-label">Di cui 4%</span>
        <span class="summary-value">€ {{ formatAmount(totalTax) }}</span>
      </div>
      <div class="summary-item">
        <span class="summary-label">Pagate</span>
        <span class="summary-value blue">{{ paidCount }}</span>
      </div>
    </div>

    <!-- Skeleton -->
    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 5" :key="i" class="skeleton-row" />
    </div>

    <!-- Empty -->
    <div v-else-if="!filtered.length" class="empty-state">
      <span>🧾</span>
      <p>Nessuna fattura trovata.</p>
      <RouterLink to="/invoices/new" class="btn-primary" style="margin-top:1rem">Crea la prima fattura</RouterLink>
    </div>

    <!-- Tabella -->
    <div v-else class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>N° Fattura</th>
            <th>Cliente</th>
            <th>Data</th>
            <th>Imponibile</th>
            <th>4%</th>
            <th>Bollo</th>
            <th>Totale</th>
            <th>Stato</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="inv in filtered" :key="inv.id">
            <td class="mono fw">{{ inv.invoice_number }}</td>
            <td>{{ inv.company_name }}</td>
            <td class="mono">{{ formatDate(inv.invoice_date) }}</td>
            <td class="mono">€ {{ formatAmount(inv.subtotal) }}</td>
            <td class="mono muted">€ {{ formatAmount(inv.tax_amount) }}</td>
            <td class="mono muted">€ {{ formatAmount(inv.stamp_duty) }}</td>
            <td class="mono green fw">€ {{ formatAmount(inv.total) }}</td>
            <td>
              <select
                :value="inv.status"
                class="status-select"
                :class="inv.status"
                @change="updateStatus(inv, $event.target.value)"
              >
                <option value="draft">Bozza</option>
                <option value="issued">Emessa</option>
                <option value="paid">Pagata</option>
              </select>
            </td>
            <td class="actions">
              <button class="btn-icon" title="Dettaglio" @click="openDetail(inv)">🔍</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Drawer dettaglio -->
    <Teleport to="body">
      <div v-if="detail.open" class="modal-overlay" @click.self="detail.open = false">
        <div class="modal modal-wide">
          <div class="modal-header">
            <h3>🧾 Fattura {{ detail.inv?.invoice_number }}</h3>
            <div style="display:flex;gap:0.5rem;align-items:center">
              <button v-if="detail.data" class="btn-pdf" @click="downloadPdf">📄 PDF</button>
              <button class="modal-close" @click="detail.open = false">✕</button>
            </div>
          </div>
          <div v-if="detail.loading" class="detail-loading">Caricamento…</div>
          <div v-else-if="detail.data" class="detail-body">
            <div class="detail-meta">
              <div><span class="meta-label">Cliente</span> {{ detail.data.company_name }}</div>
              <div><span class="meta-label">Data</span> {{ formatDate(detail.data.invoice_date) }}</div>
              <div><span class="meta-label">Stato</span> <span :class="['badge', detail.data.status]">{{ statusLabel(detail.data.status) }}</span></div>
            </div>

            <table class="data-table" style="margin-top:1rem">
              <thead>
                <tr><th>Descrizione</th><th>Ore</th><th>€/h</th><th>4%</th><th>Lordo</th></tr>
              </thead>
              <tbody>
                <tr v-for="item in detail.data.items" :key="item.id">
                  <td>{{ item.description }}</td>
                  <td class="mono">{{ item.hours }}h</td>
                  <td class="mono">€ {{ formatAmount(item.hourly_rate) }}</td>
                  <td><span :class="['pill', item.tax_inclusive ? 'in' : 'ex']">{{ item.tax_inclusive ? 'incl.' : 'escl.' }}</span></td>
                  <td class="mono green">€ {{ formatAmount(item.line_total) }}</td>
                </tr>
              </tbody>
            </table>

            <div class="totals-box">
              <div class="totals-row"><span>Imponibile</span><span class="mono">€ {{ formatAmount(detail.data.subtotal) }}</span></div>
              <div class="totals-row"><span>4% ritenuta</span><span class="mono">€ {{ formatAmount(detail.data.tax_amount) }}</span></div>
              <div class="totals-row"><span>Bollo</span><span class="mono">€ {{ formatAmount(detail.data.stamp_duty) }}</span></div>
              <div class="totals-row total"><span>TOTALE</span><span class="mono">€ {{ formatAmount(detail.data.total) }}</span></div>
            </div>

            <div v-if="detail.data.notes" class="detail-notes">📝 {{ detail.data.notes }}</div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '../services/api.js';
import { exportInvoicePdf } from '../services/pdf.js';

const invoices  = ref([]);
const loading   = ref(true);
const currentYear = new Date().getFullYear();

const filterYear   = ref(currentYear);
const filterMonth  = ref(new Date().getMonth() + 1);
const filterStatus = ref('');

const months = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

const detail = reactive({ open: false, loading: false, inv: null, data: null });

// ── Computed ─────────────────────────────────────────────
const filtered = computed(() =>
  invoices.value.filter(inv => {
    if (filterStatus.value && inv.status !== filterStatus.value) return false;
    return true;
  })
);

const totalInvoiced = computed(() => filtered.value.reduce((s, i) => s + parseFloat(i.total), 0));
const totalTax      = computed(() => filtered.value.reduce((s, i) => s + parseFloat(i.tax_amount), 0));
const paidCount     = computed(() => filtered.value.filter(i => i.status === 'paid').length);

// ── Helpers ──────────────────────────────────────────────
function formatAmount(v) { return Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function formatDate(d) { return new Date(d).toLocaleDateString('it-IT'); }
function statusLabel(s) { return { draft: 'Bozza', issued: 'Emessa', paid: 'Pagata' }[s] ?? s; }

// ── Fetch ────────────────────────────────────────────────
async function load() {
  loading.value = true;
  try {
    const params = new URLSearchParams();
    if (filterYear.value)  params.set('year',  filterYear.value);
    if (filterMonth.value) params.set('month', filterMonth.value);
    const { data } = await api.get(`/invoices?${params}`);
    invoices.value = data;
  } finally {
    loading.value = false;
  }
}

async function updateStatus(inv, status) {
  await api.put(`/invoices/${inv.id}/status`, { status });
  inv.status = status;
}

async function openDetail(inv) {
  detail.open    = true;
  detail.loading = true;
  detail.inv     = inv;
  detail.data    = null;
  try {
    const { data } = await api.get(`/invoices/${inv.id}`);
    detail.data = data;
  } finally {
    detail.loading = false;
  }
}

function downloadPdf() {
  if (detail.data) exportInvoicePdf(detail.data);
}

onMounted(load);
</script>

<style scoped>
.toolbar { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }
.select-input { padding: 0.5rem 0.75rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; background: #fff; outline: none; color: #374151; }
.select-input:focus { border-color: #0f3460; }
.btn-ghost { background: none; border: 1px solid #d1d5db; color: #6b7280; font-size: 0.875rem; cursor: pointer; padding: 0.5rem 0.75rem; border-radius: 8px; }
.btn-ghost:hover { background: #f3f4f6; }

.summary-bar { display: flex; gap: 1.5rem; flex-wrap: wrap; background: #fff; border-radius: 10px; padding: 0.875rem 1.25rem; margin-bottom: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.07); }
.summary-item { display: flex; flex-direction: column; }
.summary-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af; }
.summary-value { font-size: 1.1rem; font-weight: 700; color: #111827; font-family: 'Courier New', monospace; }
.summary-value.green { color: #059669; }
.summary-value.blue  { color: #2563eb; }

.status-select {
  padding: 0.25rem 0.5rem; border-radius: 9999px; border: 1px solid;
  font-size: 0.75rem; font-weight: 600; cursor: pointer; outline: none;
}
.status-select.draft  { background: #f3f4f6; color: #374151; border-color: #d1d5db; }
.status-select.issued { background: #dbeafe; color: #1d4ed8; border-color: #93c5fd; }
.status-select.paid   { background: #d1fae5; color: #065f46; border-color: #6ee7b7; }

.btn-primary { text-decoration: none; }

.btn-pdf { display: inline-flex; align-items: center; gap: 0.25rem; background: #eff6ff; color: #1d4ed8; border: 1px solid #93c5fd; border-radius: 8px; padding: 0.35rem 0.75rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
.btn-pdf:hover { background: #dbeafe; }

/* ── Modal / Detail ─────────────────────────────────────── */
.modal { max-width: 520px; }
.modal-wide { max-width: 700px; }

.detail-loading { padding: 2rem; text-align: center; color: #9ca3af; }
.detail-body { padding: 1.5rem; }
.detail-meta { display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151; }
.meta-label { font-weight: 700; color: #9ca3af; margin-right: 0.25rem; }

.badge.draft  { background: #f3f4f6; color: #6b7280; }
.badge.issued { background: #dbeafe; color: #1d4ed8; }
.badge.paid   { background: #d1fae5; color: #065f46; }

.pill.in { background: #d1fae5; color: #065f46; }
.pill.ex { background: #dbeafe; color: #1e40af; }

.totals-box { margin-top: 1.25rem; border-top: 2px solid #e5e7eb; padding-top: 1rem; display: flex; flex-direction: column; gap: 0.375rem; max-width: 300px; margin-left: auto; }
.totals-row { display: flex; justify-content: space-between; font-size: 0.875rem; color: #374151; }
.totals-row.total { border-top: 1px solid #e5e7eb; padding-top: 0.375rem; font-weight: 800; font-size: 1rem; color: #059669; }

.detail-notes { margin-top: 1rem; padding: 0.75rem; background: #f8fafc; border-radius: 8px; font-size: 0.875rem; color: #6b7280; }
</style>

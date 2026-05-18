<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>📄 Fatture Collaboratori</h2>
        <p class="page-sub">Fatture proforma da inviare ai collaboratori</p>
      </div>
      <RouterLink to="/collab-invoices/new" class="btn-primary">+ Nuova proforma</RouterLink>
    </div>

    <!-- Filtri -->
    <div class="toolbar">
      <input v-model="filterYear" type="number" class="select-input" placeholder="Anno" min="2020" :max="currentYear" style="width:90px" />
      <select v-model="filterMonth" class="select-input">
        <option value="">Tutti i mesi</option>
        <option v-for="(m, i) in months" :key="i+1" :value="i+1">{{ m }}</option>
      </select>
      <select v-model="filterCollab" class="select-input">
        <option value="">Tutti i collaboratori</option>
        <option v-for="c in collaborators" :key="c.id" :value="c.id">{{ c.first_name }} {{ c.last_name }}</option>
      </select>
      <select v-model="filterStatus" class="select-input">
        <option value="">Tutti gli stati</option>
        <option value="draft">Bozza</option>
        <option value="sent">Inviata</option>
        <option value="paid">Pagata</option>
      </select>
      <button class="btn-ghost" @click="load">🔄 Aggiorna</button>
    </div>

    <!-- Riepilogo -->
    <div v-if="filtered.length" class="summary-bar">
      <div class="summary-item">
        <span class="summary-label">N° proforma</span>
        <span class="summary-value">{{ filtered.length }}</span>
      </div>
      <div class="summary-item">
        <span class="summary-label">Totale lordo</span>
        <span class="summary-value green">€ {{ formatAmount(totalGross) }}</span>
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
      <span>📄</span>
      <p>Nessuna fattura proforma trovata.</p>
      <RouterLink to="/collab-invoices/new" class="btn-primary" style="margin-top:1rem">Crea la prima proforma</RouterLink>
    </div>

    <!-- Tabella -->
    <div v-else class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>N° Proforma</th>
            <th>Collaboratore</th>
            <th>Data</th>
            <th>Imponibile</th>
            <th>4%</th>
            <th>Totale</th>
            <th>Stato</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="inv in filtered" :key="inv.id">
            <td class="mono fw">{{ inv.invoice_number }}</td>
            <td>{{ inv.first_name }} {{ inv.last_name }}</td>
            <td class="mono">{{ formatDate(inv.invoice_date) }}</td>
            <td class="mono">€ {{ formatAmount(inv.subtotal) }}</td>
            <td class="mono muted">€ {{ formatAmount(inv.tax_amount) }}</td>
            <td class="mono green fw">€ {{ formatAmount(inv.total) }}</td>
            <td>
              <select
                :value="inv.status"
                class="status-select"
                :class="inv.status"
                @change="updateStatus(inv, $event.target.value)"
              >
                <option value="draft">Bozza</option>
                <option value="sent">Inviata</option>
                <option value="paid">Pagata</option>
              </select>
            </td>
            <td class="actions">
              <button class="btn-icon" title="Dettaglio" @click="openDetail(inv)">🔍</button>
              <button class="btn-icon" title="Elimina" @click="remove(inv)">🗑️</button>
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
            <h3>📄 Proforma {{ detail.inv?.invoice_number }}</h3>
            <div style="display:flex;gap:0.5rem;align-items:center">
              <button v-if="detail.data" class="btn-pdf" @click="downloadPdf">📄 PDF</button>
              <button class="modal-close" @click="detail.open = false">✕</button>
            </div>
          </div>
          <div v-if="detail.loading" class="detail-loading">Caricamento…</div>
          <div v-else-if="detail.data" class="detail-body">
            <div class="detail-meta">
              <div><span class="meta-label">Collaboratore</span> {{ detail.data.first_name }} {{ detail.data.last_name }}</div>
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
import { exportCollabInvoicePdf } from '../services/pdf.js';

const invoices      = ref([]);
const collaborators = ref([]);
const loading       = ref(true);
const currentYear   = new Date().getFullYear();

const filterYear    = ref(currentYear);
const filterMonth   = ref(new Date().getMonth() + 1);
const filterCollab  = ref('');
const filterStatus  = ref('');

const months = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

const detail = reactive({ open: false, loading: false, inv: null, data: null });

const filtered = computed(() =>
  invoices.value.filter(inv => {
    if (filterCollab.value && inv.collaborator_id != filterCollab.value) return false;
    if (filterStatus.value && inv.status !== filterStatus.value) return false;
    return true;
  })
);

const totalGross = computed(() => filtered.value.reduce((s, i) => s + parseFloat(i.total), 0));
const paidCount  = computed(() => filtered.value.filter(i => i.status === 'paid').length);

function formatAmount(v) { return Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function formatDate(d) { return new Date(d).toLocaleDateString('it-IT'); }
function statusLabel(s) { return { draft: 'Bozza', sent: 'Inviata', paid: 'Pagata' }[s] ?? s; }

async function load() {
  loading.value = true;
  try {
    const params = new URLSearchParams();
    if (filterYear.value)  params.set('year',  filterYear.value);
    if (filterMonth.value) params.set('month', filterMonth.value);
    const [inv, collab] = await Promise.all([
      api.get(`/collab-invoices?${params}`),
      api.get('/collaborators'),
    ]);
    invoices.value      = inv.data;
    collaborators.value = collab.data;
  } finally {
    loading.value = false;
  }
}

async function updateStatus(inv, status) {
  await api.put(`/collab-invoices/${inv.id}/status`, { status });
  inv.status = status;
}

async function openDetail(inv) {
  detail.open    = true;
  detail.loading = true;
  detail.inv     = inv;
  detail.data    = null;
  try {
    const { data } = await api.get(`/collab-invoices/${inv.id}`);
    detail.data = data;
  } finally {
    detail.loading = false;
  }
}

function downloadPdf() {
  if (detail.data) exportCollabInvoicePdf(detail.data);
}

async function remove(inv) {
  if (!confirm(`Eliminare la proforma ${inv.invoice_number}?`)) return;
  await api.delete(`/collab-invoices/${inv.id}`);
  await load();
}

onMounted(load);
</script>

<style scoped>
.page { padding: 2rem; max-width: 1200px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap; }
.page-header h2 { font-size: 1.5rem; font-weight: 700; color: #111827; }
.page-sub { font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem; }

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

.table-wrapper { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th { text-align: left; padding: 0.75rem; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; border-bottom: 1px solid #e5e7eb; background: #f9fafb; }
.data-table td { padding: 0.65rem 0.75rem; border-bottom: 1px solid #f3f4f6; color: #374151; vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover td { background: #f9fafb; }
.mono  { font-family: 'Courier New', monospace; }
.fw    { font-weight: 600; }
.green { color: #059669; font-weight: 600; }
.muted { color: #9ca3af; }

.status-select { padding: 0.25rem 0.5rem; border-radius: 9999px; border: 1px solid; font-size: 0.75rem; font-weight: 600; cursor: pointer; outline: none; }
.status-select.draft  { background: #f3f4f6; color: #374151; border-color: #d1d5db; }
.status-select.sent   { background: #fef9c3; color: #854d0e; border-color: #fde047; }
.status-select.paid   { background: #d1fae5; color: #065f46; border-color: #6ee7b7; }

.actions { display: flex; gap: 0.25rem; }
.btn-icon { background: none; border: none; cursor: pointer; padding: 0.25rem 0.375rem; border-radius: 6px; font-size: 1rem; transition: background 0.15s; }
.btn-icon:hover { background: #f3f4f6; }

.btn-primary { display: inline-flex; align-items: center; gap: 0.375rem; background: linear-gradient(135deg, #0f3460, #1a6fb5); color: #fff; border: none; border-radius: 8px; padding: 0.6rem 1.1rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: opacity 0.2s; text-decoration: none; white-space: nowrap; }
.btn-primary:hover { opacity: 0.9; }

.btn-pdf { display: inline-flex; align-items: center; gap: 0.25rem; background: #eff6ff; color: #1d4ed8; border: 1px solid #93c5fd; border-radius: 8px; padding: 0.35rem 0.75rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
.btn-pdf:hover { background: #dbeafe; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 500; padding: 1rem; }
.modal { background: #fff; border-radius: 16px; width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
.modal-wide { max-width: 700px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid #e5e7eb; }
.modal-header h3 { font-size: 1.1rem; font-weight: 700; color: #111827; }
.modal-close { background: none; border: none; font-size: 1.1rem; cursor: pointer; color: #6b7280; padding: 0.25rem; border-radius: 4px; }
.modal-close:hover { background: #f3f4f6; }

.detail-loading { padding: 2rem; text-align: center; color: #9ca3af; }
.detail-body { padding: 1.5rem; }
.detail-meta { display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151; }
.meta-label { font-weight: 700; color: #9ca3af; margin-right: 0.25rem; }

.badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
.badge.draft  { background: #f3f4f6; color: #6b7280; }
.badge.sent   { background: #fef9c3; color: #854d0e; }
.badge.paid   { background: #d1fae5; color: #065f46; }

.pill { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 9999px; font-size: 0.68rem; font-weight: 600; }
.pill.in { background: #d1fae5; color: #065f46; }
.pill.ex { background: #dbeafe; color: #1e40af; }

.totals-box { margin-top: 1.25rem; border-top: 2px solid #e5e7eb; padding-top: 1rem; display: flex; flex-direction: column; gap: 0.375rem; max-width: 300px; margin-left: auto; }
.totals-row { display: flex; justify-content: space-between; font-size: 0.875rem; color: #374151; }
.totals-row.total { border-top: 1px solid #e5e7eb; padding-top: 0.375rem; font-weight: 800; font-size: 1rem; color: #059669; }
.detail-notes { margin-top: 1rem; padding: 0.75rem; background: #f8fafc; border-radius: 8px; font-size: 0.875rem; color: #6b7280; }

.skeleton-list { display: flex; flex-direction: column; gap: 0.5rem; }
.skeleton-row { height: 3rem; border-radius: 8px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.2s infinite; }
@keyframes shimmer { to { background-position: -200% 0; } }

.empty-state { text-align: center; padding: 4rem 2rem; color: #9ca3af; }
.empty-state span { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; }

@media (max-width: 768px) { .page { padding: 1rem; } }
</style>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>📄 Le mie fatture</h2>
        <p class="page-sub">Fatture proforma ricevute dall'amministratore</p>
      </div>
    </div>

    <!-- Skeleton -->
    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 4" :key="i" class="skeleton-row" />
    </div>

    <!-- Empty -->
    <div v-else-if="!invoices.length" class="empty-state">
      <span>📄</span>
      <p>Nessuna fattura proforma ricevuta.</p>
    </div>

    <!-- Lista fatture -->
    <div v-else class="invoices-list">
      <div v-for="inv in invoices" :key="inv.id" class="invoice-card" :class="inv.status">
        <div class="inv-top">
          <div class="inv-number">{{ inv.invoice_number }}</div>
          <span :class="['status-badge', inv.status]">{{ statusLabel(inv.status) }}</span>
        </div>

        <div class="inv-meta">
          <span>📅 {{ formatDate(inv.invoice_date) }}</span>
          <span v-if="inv.paid_at" class="paid-date">✅ Pagata il {{ formatDate(inv.paid_at) }}</span>
        </div>

        <div class="inv-amounts">
          <div class="amount-row">
            <span class="amount-label">Imponibile</span>
            <span class="mono">€ {{ formatAmount(inv.subtotal) }}</span>
          </div>
          <div class="amount-row">
            <span class="amount-label">4% ritenuta</span>
            <span class="mono muted">€ {{ formatAmount(inv.tax_amount) }}</span>
          </div>
          <div class="amount-row total">
            <span class="amount-label">Totale</span>
            <span class="mono green">€ {{ formatAmount(inv.total) }}</span>
          </div>
        </div>

        <div v-if="inv.notes" class="inv-notes">📝 {{ inv.notes }}</div>

        <div class="inv-actions">
          <button class="btn-ghost" @click="openDetail(inv)">🔍 Dettaglio</button>
          <div v-if="inv.status === 'sent'" class="mark-paid-form">
            <input
              v-model="paidDates[inv.id]"
              type="date"
              class="date-input"
              :placeholder="today"
              :max="today"
            />
            <button class="btn-mark-paid" @click="markPaid(inv)" :disabled="marking[inv.id]">
              <span v-if="marking[inv.id]" class="spinner" />
              {{ marking[inv.id] ? 'Salvataggio…' : '✅ Segna come pagata' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Drawer dettaglio -->
    <Teleport to="body">
      <div v-if="detail.open" class="modal-overlay" @click.self="detail.open = false">
        <div class="modal">
          <div class="modal-header">
            <h3>📄 Proforma {{ detail.inv?.invoice_number }}</h3>
            <button class="modal-close" @click="detail.open = false">✕</button>
          </div>
          <div v-if="detail.loading" class="detail-loading">Caricamento…</div>
          <div v-else-if="detail.data" class="modal-form">
            <div class="detail-meta">
              <div><span class="meta-label">Data:</span> {{ formatDate(detail.data.invoice_date) }}</div>
              <div><span class="meta-label">Stato:</span> <span :class="['status-badge', detail.data.status]">{{ statusLabel(detail.data.status) }}</span></div>
              <div v-if="detail.data.paid_at"><span class="meta-label">Pagata il:</span> {{ formatDate(detail.data.paid_at) }}</div>
            </div>

            <table class="detail-table">
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

            <div class="detail-totals">
              <div class="totals-row"><span>Imponibile</span><span class="mono">€ {{ formatAmount(detail.data.subtotal) }}</span></div>
              <div class="totals-row"><span>4% ritenuta</span><span class="mono muted">€ {{ formatAmount(detail.data.tax_amount) }}</span></div>
              <div class="totals-row grand"><span>TOTALE</span><span class="mono green">€ {{ formatAmount(detail.data.total) }}</span></div>
            </div>

            <div v-if="detail.data.notes" class="detail-notes">📝 {{ detail.data.notes }}</div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../services/api.js';

const invoices  = ref([]);
const loading   = ref(true);
const paidDates = reactive({});
const marking   = reactive({});
const today     = new Date().toISOString().slice(0, 10);
const detail    = reactive({ open: false, loading: false, inv: null, data: null });

function formatAmount(v) { return Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function formatDate(d)   { return d ? new Date(d).toLocaleDateString('it-IT') : '—'; }
function statusLabel(s)  { return { draft: 'Bozza', sent: 'Inviata', paid: 'Pagata' }[s] ?? s; }

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/collab-invoices/mine');
    invoices.value = data;
  } finally {
    loading.value = false;
  }
}

async function markPaid(inv) {
  marking[inv.id] = true;
  try {
    const paid_at = paidDates[inv.id] || today;
    await api.put(`/collab-invoices/mine/${inv.id}/paid`, { paid_at });
    inv.status  = 'paid';
    inv.paid_at = paid_at;
  } finally {
    marking[inv.id] = false;
  }
}

async function openDetail(inv) {
  detail.open    = true;
  detail.loading = true;
  detail.inv     = inv;
  detail.data    = null;
  try {
    const { data } = await api.get(`/collab-invoices/mine/${inv.id}`);
    detail.data = data;
  } finally {
    detail.loading = false;
  }
}

onMounted(load);
</script>

<style scoped>
.invoices-list { display: flex; flex-direction: column; gap: 1rem; }

.invoice-card {
  background: #fff;
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  border-left: 4px solid #e5e7eb;
}

.invoice-card.sent { border-left-color: #f59e0b; }
.invoice-card.paid { border-left-color: #10b981; }

.inv-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
.inv-number { font-size: 1rem; font-weight: 700; color: #111827; }

.status-badge {
  display: inline-block; padding: 0.2rem 0.6rem;
  border-radius: 9999px; font-size: 0.75rem; font-weight: 600;
}
.status-badge.sent { background: #fef3c7; color: #92400e; }
.status-badge.paid { background: #d1fae5; color: #065f46; }
.status-badge.draft { background: #f3f4f6; color: #6b7280; }

.inv-meta { display: flex; gap: 1rem; font-size: 0.85rem; color: #6b7280; margin-bottom: 0.75rem; flex-wrap: wrap; }
.paid-date { color: #059669; font-weight: 600; }

.inv-amounts { display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 0.75rem; }
.amount-row { display: flex; justify-content: space-between; font-size: 0.875rem; color: #374151; }
.amount-row.total { border-top: 1px solid #e5e7eb; padding-top: 0.375rem; margin-top: 0.25rem; font-weight: 700; font-size: 1rem; }
.amount-label { color: #6b7280; }

.inv-notes { font-size: 0.85rem; color: #6b7280; margin-bottom: 0.75rem; }

.inv-actions { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }

.mark-paid-form { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }

.date-input {
  padding: 0.4rem 0.6rem;
  border: 1.5px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.85rem;
  outline: none;
  color: #374151;
}
.date-input:focus { border-color: #10b981; }

.btn-mark-paid {
  display: inline-flex; align-items: center; gap: 0.375rem;
  background: #10b981; color: #fff; border: none;
  border-radius: 8px; padding: 0.45rem 0.875rem;
  font-size: 0.875rem; font-weight: 600; cursor: pointer;
  transition: opacity 0.2s;
}
.btn-mark-paid:hover:not(:disabled) { opacity: 0.9; }
.btn-mark-paid:disabled { opacity: 0.6; cursor: not-allowed; }

.detail-loading { padding: 2rem; text-align: center; color: #9ca3af; }

.detail-meta { display: flex; flex-direction: column; gap: 0.375rem; font-size: 0.875rem; color: #374151; margin-bottom: 1rem; }
.meta-label { font-weight: 600; margin-right: 0.25rem; }

.detail-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-bottom: 1rem; }
.detail-table th { text-align: left; padding: 0.5rem; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
.detail-table td { padding: 0.5rem; border-bottom: 1px solid #f3f4f6; color: #374151; }

.detail-totals { border-top: 1px solid #e5e7eb; padding-top: 0.75rem; display: flex; flex-direction: column; gap: 0.375rem; }
.totals-row { display: flex; justify-content: space-between; font-size: 0.875rem; color: #374151; }
.totals-row.grand { border-top: 2px solid #e5e7eb; padding-top: 0.5rem; font-weight: 800; font-size: 1.05rem; margin-top: 0.25rem; }

.detail-notes { margin-top: 1rem; font-size: 0.85rem; color: #6b7280; }

.pill { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 9999px; font-size: 0.68rem; font-weight: 600; }
.pill.in { background: #d1fae5; color: #065f46; }
.pill.ex { background: #dbeafe; color: #1e40af; }
</style>

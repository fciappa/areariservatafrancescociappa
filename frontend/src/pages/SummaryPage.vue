<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>📅 Riepilogo Mensile</h2>
        <p class="page-sub">Panoramica delle attività per mese</p>
      </div>
      <!-- Selettore mese -->
      <input v-if="auth.isAdmin" v-model="selectedMonth" type="month" class="month-picker" />
    </div>

    <!-- ── VISTA ADMIN ─────────────────────────────────── -->
    <template v-if="auth.isAdmin">

      <!-- KPI mese -->
      <div v-if="loading" class="kpi-grid">
        <div v-for="i in 4" :key="i" class="kpi-skeleton" />
      </div>
      <div v-else class="kpi-grid">
        <div class="kpi-card green">
          <div class="kpi-icon">💶</div>
          <div class="kpi-value mono">€ {{ fmt(adminData.invoiced) }}</div>
          <div class="kpi-label">Fatturato del mese</div>
          <div class="kpi-sub">{{ adminData.invoiceCount }} fatture emesse</div>
        </div>
        <div class="kpi-card blue">
          <div class="kpi-icon">⏱️</div>
          <div class="kpi-value mono">{{ adminData.myHours }}h</div>
          <div class="kpi-label">Ore fatturabili</div>
          <div class="kpi-sub">lavorate per i clienti</div>
        </div>
        <div class="kpi-card orange">
          <div class="kpi-icon">🕐</div>
          <div class="kpi-value mono">{{ adminData.collabHours }}h</div>
          <div class="kpi-label">Ore collaboratori</div>
          <div class="kpi-sub">da liquidare: € {{ fmt(adminData.collabCost) }}</div>
        </div>
        <div class="kpi-card purple">
          <div class="kpi-icon">📊</div>
          <div class="kpi-value mono">€ {{ fmt(adminData.invoiced - adminData.collabCost) }}</div>
          <div class="kpi-label">Margine stimato</div>
          <div class="kpi-sub">fatturato − costo collaboratori</div>
        </div>
      </div>

      <div class="two-col">
        <!-- Fatture del mese -->
        <section class="card">
          <div class="card-header">
            <h3>🧾 Fatture del mese</h3>
            <RouterLink to="/invoices" class="card-link">Vedi tutte →</RouterLink>
          </div>
          <div v-if="loading" class="skeleton-list"><div v-for="i in 3" :key="i" class="skeleton-row" /></div>
          <div v-else-if="!adminData.invoices.length" class="empty-small">Nessuna fattura questo mese.</div>
          <table v-else class="mini-table">
            <thead><tr><th>N°</th><th>Cliente</th><th>Totale</th><th>Stato</th></tr></thead>
            <tbody>
              <tr v-for="inv in adminData.invoices" :key="inv.id">
                <td class="mono">{{ inv.invoice_number }}</td>
                <td>{{ inv.company_name }}</td>
                <td class="mono green">€ {{ fmt(inv.total) }}</td>
                <td><span :class="['badge', inv.status]">{{ statusLabel(inv.status) }}</span></td>
              </tr>
            </tbody>
          </table>
        </section>

        <!-- Ore per collaboratore -->
        <section class="card">
          <div class="card-header">
            <h3>👥 Ore per collaboratore</h3>
            <RouterLink to="/collab-hours" class="card-link">Gestisci →</RouterLink>
          </div>
          <div v-if="loading" class="skeleton-list"><div v-for="i in 3" :key="i" class="skeleton-row" /></div>
          <div v-else-if="!adminData.collabSummary.length" class="empty-small">Nessuna ora registrata.</div>
          <table v-else class="mini-table">
            <thead><tr><th>Collaboratore</th><th>Ore</th><th>Da pagare</th></tr></thead>
            <tbody>
              <tr v-for="c in adminData.collabSummary" :key="c.collaborator_id">
                <td class="fw">{{ c.first_name }} {{ c.last_name }}</td>
                <td class="mono">{{ c.total_hours }}h</td>
                <td class="mono green">€ {{ fmt(c.total_cost) }}</td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>

      <!-- Storico mensile -->
      <section class="card">
        <h3 class="card-title-sm">📈 Storico fatturato (ultimi mesi)</h3>
        <div v-if="loading" class="skeleton-list"><div v-for="i in 6" :key="i" class="skeleton-row" /></div>
        <div v-else-if="!monthlySummary.length" class="empty-small">Nessun dato storico.</div>
        <div v-else class="chart-container">
          <div
            v-for="m in monthlySummary"
            :key="`${m.year}-${m.month}`"
            class="chart-bar-wrap"
          >
            <div class="chart-bar-label">€ {{ fmtShort(m.total_invoiced) }}</div>
            <div
              class="chart-bar"
              :style="{ height: barHeight(m.total_invoiced) + 'px' }"
              :class="{ current: isCurrentMonth(m) }"
              :title="`${monthName(m.month)} ${m.year}: € ${fmt(m.total_invoiced)}`"
            />
            <div class="chart-bar-month">{{ monthShort(m.month) }}<br/>{{ m.year }}</div>
          </div>
        </div>
      </section>
    </template>

    <!-- ── VISTA COLLABORATORE ─────────────────────────── -->
    <template v-else>
      <div v-if="loading" class="kpi-grid">
        <div v-for="i in 3" :key="i" class="kpi-skeleton" />
      </div>
      <div v-else class="kpi-grid">
        <div class="kpi-card blue">
          <div class="kpi-icon">⏱️</div>
          <div class="kpi-value mono">{{ collabData.totalHours }}h</div>
          <div class="kpi-label">Ore lavorate</div>
          <div class="kpi-sub">{{ monthLabel }}</div>
        </div>
        <div class="kpi-card orange">
          <div class="kpi-icon">💶</div>
          <div class="kpi-value mono">€ {{ fmt(collabData.pendingGross) }}</div>
          <div class="kpi-label">Da fatturare (lordo)</div>
          <div class="kpi-sub">di cui 4%: € {{ fmt(collabData.pendingTax) }}</div>
        </div>
        <div class="kpi-card green">
          <div class="kpi-icon">🧾</div>
          <div class="kpi-value mono">€ {{ fmt(collabData.invoicedGross) }}</div>
          <div class="kpi-label">Già fatturate</div>
          <div class="kpi-sub">ore incluse in proforma</div>
        </div>
      </div>

      <section class="card">
        <div class="card-header">
          <h3>🗓️ Dettaglio ore — {{ monthLabel }}</h3>
          <input
            :value="monthDateValue"
            type="date"
            class="month-picker"
            @input="onMonthDateInput"
          />
        </div>
        <div v-if="loading" class="skeleton-list"><div v-for="i in 5" :key="i" class="skeleton-row" /></div>
        <div v-else-if="!collabData.hours.length" class="empty-small">Nessuna ora registrata questo mese.</div>
        <table v-else class="mini-table">
          <thead><tr><th>Data</th><th>Ore</th><th>Progetto</th><th>Tariffa</th><th>€/h</th><th>Lordo</th><th>4%</th><th></th></tr></thead>
          <tbody>
            <tr v-for="h in collabData.hours" :key="h.id" :class="{ 'row-invoiced': h.invoiced_at }">
              <td class="mono">{{ formatDate(h.work_date) }}</td>
              <td class="mono">{{ h.hours }}h</td>
              <td>{{ h.project_name || '—' }}</td>
              <td>{{ h.tariff_name }}</td>
              <td class="mono">€ {{ fmt(h.hourly_rate) }}</td>
              <td class="mono green">€ {{ fmt(calcGross(h)) }}</td>
              <td class="mono muted">€ {{ fmt(calcTax(h)) }}</td>
              <td class="invoiced-cell">
                <span v-if="h.invoiced_at" class="inv-badge" title="Inclusa in proforma">🧾</span>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="tfoot-total">
              <td colspan="2" class="mono">{{ collabData.totalHours }}h</td>
              <td colspan="3"></td>
              <td class="mono green fw">€ {{ fmt(collabData.totalGross) }}</td>
              <td class="mono muted">€ {{ fmt(collabData.totalTax) }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import api from '../services/api.js';

const auth    = useAuthStore();
const loading = ref(true);

const now = new Date();
const selectedMonth = ref(now.toISOString().slice(0, 7));

const monthNames  = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
const monthShorts = ['Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'];

const monthLabel = computed(() => {
  const [y, m] = selectedMonth.value.split('-');
  return `${monthNames[parseInt(m) - 1]} ${y}`;
});

const monthDateValue = computed(() => `${selectedMonth.value}-01`);

// ── Admin data ────────────────────────────────────────────
const adminData = reactive({
  invoiced: 0, invoiceCount: 0, myHours: 0, collabHours: 0,
  collabCost: 0, invoices: [], collabSummary: [],
});
const monthlySummary = ref([]);

// ── Collab data ───────────────────────────────────────────
const collabData = reactive({
  totalHours: 0,
  totalGross: 0, totalTax: 0,
  pendingGross: 0, pendingTax: 0,
  invoicedGross: 0,
  hours: [],
});

// ── Helpers ──────────────────────────────────────────────
function fmt(v)      { return Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function fmtShort(v) { return Number(v ?? 0) >= 1000 ? (Number(v)/1000).toFixed(1) + 'k' : fmt(v); }
function formatDate(d) { return new Date(d).toLocaleDateString('it-IT'); }
function statusLabel(s) { return { draft: 'Bozza', issued: 'Emessa', paid: 'Pagata' }[s] ?? s; }
function monthName(m)  { return monthNames[parseInt(m) - 1]; }
function monthShort(m) { return monthShorts[parseInt(m) - 1]; }

function onMonthDateInput(event) {
  const value = event.target?.value ?? '';
  if (!value) return;
  selectedMonth.value = value.slice(0, 7);
}

function calcGross(h) {
  const rate = h.rate_type === 'daily' ? parseFloat(h.hourly_rate) / 8 : parseFloat(h.hourly_rate);
  return parseFloat(h.hours) * rate;
}
function calcTax(h)   { const g = calcGross(h); return h.tax_inclusive ? g - g / 1.04 : g * 0.04; }

function isCurrentMonth(m) {
  const [y, mo] = selectedMonth.value.split('-');
  return m.year == y && m.month == mo;
}

const maxInvoiced = computed(() => Math.max(...monthlySummary.value.map(m => m.total_invoiced), 1));
function barHeight(v) { return Math.max(10, (parseFloat(v) / maxInvoiced.value) * 120); }

// ── Fetch ────────────────────────────────────────────────
async function loadAdmin() {
  const [year, month] = selectedMonth.value.split('-');

  const [invoices, myHours, collabHours, summary] = await Promise.allSettled([
    api.get(`/invoices?year=${year}&month=${month}`),
    api.get('/hours/my'),
    api.get('/hours/collaborators'),
    api.get('/invoices/summary/monthly'),
  ]);

  if (invoices.status === 'fulfilled') {
    adminData.invoices      = invoices.value.data;
    adminData.invoiced      = invoices.value.data.filter(i => i.status !== 'draft').reduce((s, i) => s + parseFloat(i.total), 0);
    adminData.invoiceCount  = invoices.value.data.filter(i => i.status !== 'draft').length;
  }

  if (myHours.status === 'fulfilled') {
    const filtered = myHours.value.data.filter(h => h.work_date.slice(0, 7) === selectedMonth.value);
    adminData.myHours = filtered.reduce((s, h) => s + parseFloat(h.hours), 0);
  }

  if (collabHours.status === 'fulfilled') {
    const filtered = collabHours.value.data.filter(h => h.work_date.slice(0, 7) === selectedMonth.value);
    adminData.collabHours = filtered.reduce((s, h) => s + parseFloat(h.hours), 0);
    adminData.collabCost  = filtered.reduce((s, h) => s + calcGross(h), 0);

    // Raggruppa per collaboratore
    const map = {};
    for (const h of filtered) {
      if (!map[h.collaborator_id]) map[h.collaborator_id] = { collaborator_id: h.collaborator_id, first_name: h.first_name, last_name: h.last_name, total_hours: 0, total_cost: 0 };
      map[h.collaborator_id].total_hours += parseFloat(h.hours);
      map[h.collaborator_id].total_cost  += calcGross(h);
    }
    adminData.collabSummary = Object.values(map);
  }

  if (summary.status === 'fulfilled') {
    monthlySummary.value = summary.value.data.slice(0, 12).reverse();
  }
}

async function loadCollab() {
  const { data } = await api.get('/hours/collaborators');
  const filtered = data.filter(h => h.work_date.slice(0, 7) === selectedMonth.value);
  const pending  = filtered.filter(h => !h.invoiced_at && h.status !== 'rejected');
  const invoiced = filtered.filter(h =>  h.invoiced_at);

  collabData.hours         = filtered;
  collabData.totalHours    = filtered.reduce((s, h) => s + parseFloat(h.hours), 0);
  collabData.totalGross    = filtered.reduce((s, h) => s + calcGross(h), 0);
  collabData.totalTax      = filtered.reduce((s, h) => s + calcTax(h), 0);
  collabData.pendingGross  = pending.reduce((s, h) => s + calcGross(h), 0);
  collabData.pendingTax    = pending.reduce((s, h) => s + calcTax(h), 0);
  collabData.invoicedGross = invoiced.reduce((s, h) => s + calcGross(h), 0);
}

async function load() {
  loading.value = true;
  try {
    if (auth.isAdmin) await loadAdmin();
    else              await loadCollab();
  } finally {
    loading.value = false;
  }
}

watch(selectedMonth, load);
onMounted(load);
</script>

<style scoped>

.month-picker { padding: 0.5rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; outline: none; background: #fff; color: #374151; }
.month-picker:focus { border-color: #0f3460; }

/* ── KPI ────────────────────────────────────────────────── */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.kpi-card { background: #fff; border-radius: 12px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border-left: 4px solid #d1d5db; }
.kpi-card.green  { border-color: #10b981; }
.kpi-card.blue   { border-color: #3b82f6; }
.kpi-card.orange { border-color: #f59e0b; }
.kpi-card.purple { border-color: #8b5cf6; }
.kpi-icon  { font-size: 1.5rem; margin-bottom: 0.5rem; }
.kpi-value { font-size: 1.5rem; font-weight: 700; color: #111827; }
.kpi-label { font-size: 0.8rem; font-weight: 600; color: #374151; margin-top: 0.25rem; }
.kpi-sub   { font-size: 0.75rem; color: #9ca3af; margin-top: 0.125rem; }
.kpi-skeleton { height: 120px; border-radius: 12px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.2s infinite; }

/* ── Two col ────────────────────────────────────────────── */
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
@media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }

/* ── Card ───────────────────────────────────────────────── */
.card { margin-bottom: 1rem; }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.card-header h3  { font-size: 0.95rem; font-weight: 700; color: #111827; }
.card-link       { font-size: 0.8rem; color: #0f3460; font-weight: 600; text-decoration: none; }
.card-link:hover { text-decoration: underline; }
.card-title-sm   { font-size: 0.95rem; font-weight: 700; color: #111827; margin-bottom: 1.25rem; }

/* ── Mini table ─────────────────────────────────────────── */
.mini-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.mini-table th { text-align: left; padding: 0.375rem 0.5rem; font-size: 0.72rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #e5e7eb; }
.mini-table td { padding: 0.5rem; border-bottom: 1px solid #f3f4f6; color: #374151; }
.mini-table tbody tr:last-child td { border-bottom: none; }
.mini-table tfoot td { border-top: 2px solid #e5e7eb; font-weight: 700; padding-top: 0.625rem; }

.badge.draft  { background: #f3f4f6; color: #6b7280; }
.badge.issued { background: #dbeafe; color: #1d4ed8; }
.badge.paid   { background: #d1fae5; color: #065f46; }

/* ── Chart ──────────────────────────────────────────────── */
.chart-container { display: flex; align-items: flex-end; gap: 0.5rem; padding: 0.5rem 0; overflow-x: auto; }
.chart-bar-wrap  { display: flex; flex-direction: column; align-items: center; gap: 0.25rem; flex-shrink: 0; }
.chart-bar-label { font-size: 0.68rem; color: #6b7280; white-space: nowrap; }
.chart-bar {
  width: 40px; background: #dbeafe; border-radius: 4px 4px 0 0;
  transition: height 0.4s ease; cursor: pointer;
}
.chart-bar.current { background: #2563eb; }
.chart-bar:hover   { opacity: 0.8; }
.chart-bar-month   { font-size: 0.68rem; color: #9ca3af; text-align: center; line-height: 1.3; }

.empty-small { text-align: center; padding: 1.5rem; color: #9ca3af; font-size: 0.875rem; }

.row-invoiced td { background: #fefce8 !important; }
.invoiced-cell { width: 1.5rem; text-align: center; }
.inv-badge { font-size: 0.9rem; cursor: default; }
</style>
